<?php

namespace TrueLoyal\LoyaltyRewards\Model;

use Firebase\JWT\JWT;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\SessionFactory;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use TrueLoyal\LoyaltyRewards\Helper\Config;

/**
 * Signs the storefront widget JWT for the current visitor.
 * It holds the member's PII, so it must only reach the browser through private (uncached) content.
 */
class MemberJwt
{
    /**
     * Token lifetime in seconds. TrueLoyal reads `exp` in seconds.
     */
    public const LIFETIME = 3600;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var JWT
     */
    private $jwt;

    /**
     * @var CustomerFactory
     */
    private $customerFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var SessionFactory
     */
    private $sessionFactory;

    /**
     * @var CountryFactory
     */
    private $countryFactory;

    /**
     * @var Resolver
     */
    private $store;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * MemberJwt constructor.
     *
     * @param Config $config
     * @param JWT $jwt
     * @param CustomerFactory $customerFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param SessionFactory $sessionFactory
     * @param CountryFactory $countryFactory
     * @param Resolver $store
     * @param SerializerInterface $serializer
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Config $config,
        JWT $jwt,
        CustomerFactory $customerFactory,
        CustomerRepositoryInterface $customerRepository,
        SessionFactory $sessionFactory,
        CountryFactory $countryFactory,
        Resolver $store,
        SerializerInterface $serializer,
        StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->jwt = $jwt;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->sessionFactory = $sessionFactory;
        $this->countryFactory = $countryFactory;
        $this->store = $store;
        $this->serializer = $serializer;
        $this->storeManager = $storeManager;
    }

    /**
     * Build and return a signed JWT for the current customer.
     * Returns empty string if the customer is not eligible.
     *
     * @param int $expiresAt Unix timestamp in seconds
     * @return string
     */
    public function create(int $expiresAt): string
    {
        if ($this->config->isDashboardHiddenForGuests() && !$this->sessionFactory->create()->getCustomerId()) {
            return '';
        }

        $key = $this->config->getApiKey();
        $apiKeyIdentifier = $this->config->getApiKeyIdentifier();
        $customerEmail = "";
        $customerFirstName = "";
        $customerLastName = "";
        $customerBirthDate = "";
        $street = [];
        $city = "";
        $region = "";
        $postcode = "";
        $country = "";
        $telephone = "";
        $lang = "";
        $memberIdentifierValue = "";

        $jsonConfigLanguage = $this->config->getConfigLanguage();
        $preferredLanguage = $this->config->getPreferredLanguage();
        if ($preferredLanguage) {
            $lang = $preferredLanguage;
        } elseif ($jsonConfigLanguage) {
            $lang = $this->store->getLocale() ?? "";
            $configLanguage = $this->serializer->unserialize($jsonConfigLanguage);
            $config = stristr($lang, "_", true);
            if (isset($configLanguage[$config]) && $configLanguage[$config]) {
                $lang = $configLanguage[$config];
            } else {
                $lang = "";
            }
        }

        $customerId = $this->sessionFactory->create()->getCustomerId();
        if ($customerId && !$this->config->isAutoEnrollmentEnabled()) {
            $customerData = $this->customerRepository->getById($customerId);
            $optInAttribute = $this->config->getOptInAttributeCode();
            $trueloyalOptedIn = $customerData->getCustomAttribute($optInAttribute);
            if (!$trueloyalOptedIn || !$trueloyalOptedIn->getValue()) {
                return '';
            }
        }

        $customer = $this->customerFactory->create()->load($customerId);
        if ($customer->getEntityId()) {
            $billingAddress = $customer->getDefaultBillingAddress()
                ? $customer->getDefaultBillingAddress()->getData()
                : [];
            $customerEmail = $customer->getEmail();
            $customerFirstName = $customer->getFirstname();
            $customerLastName = $customer->getLastname();
            $customerBirthDate = $customer->getDob();
            $pattern = '/(\d{4})-(\d{2})-(\d{2})/';
            if ($customerBirthDate && preg_match($pattern, $customerBirthDate)) {
                $customerBirthDate = preg_replace($pattern, '$2/$3/$1', $customerBirthDate);
            }
            $telephone = $billingAddress['telephone'] ?? "";
            $city = $billingAddress['city'] ?? "";
            $region = $billingAddress['region'] ?? "";
            $postcode = $billingAddress['postcode'] ?? "";
            $street = isset($billingAddress['street']) ? explode("\n", $billingAddress['street']) : [];
            $country = isset($billingAddress['country_id'])
                ? $this->getCountryName($billingAddress['country_id'])
                : "";
            $memberIdentifierValue = $this->config->getMemberIdentifierValue();
        }

        $customAttributes = [];
        $customStoreIdKey = $this->config->getCustomMemberStoreId();
        $customStoreCodeKey = $this->config->getCustomMemberStoreCode();
        $customStoreCurrencyKey = $this->config->getCustomMemberStoreCurrency();
        if ($customStoreIdKey) {
            $customAttributes[$customStoreIdKey] = $this->storeManager->getGroup()->getId();
        }
        if ($customStoreCodeKey) {
            $customAttributes[$customStoreCodeKey] = $this->storeManager->getGroup()->getCode();
        }
        if ($customStoreCurrencyKey) {
            $customAttributes[$customStoreCurrencyKey] = $this->config->getStoreCurrencyCode();
        }

        $payload = [
            'member_id'          => $memberIdentifierValue,
            'sub'                => $apiKeyIdentifier,
            'email_address'      => $customerEmail,
            'first_name'         => $customerFirstName,
            'last_name'          => $customerLastName,
            'phone_number'       => $telephone
                ? (preg_match('/^\+[0-9]{2}-[0-9]{10}+$/', $telephone) ? $telephone : "")
                : "",
            'birthdate'          => $customerBirthDate,
            'preferred_language' => $lang,
            'address'            => [
                'line1'       => $street[0] ?? "",
                'line2'       => $street[1] ?? ($street[0] ?? ""),
                'city'        => $city,
                'state'       => $region,
                'country'     => $country,
                'postal_code' => $postcode,
            ],
            'custom_attributes'  => $customAttributes,
            'exp'                => $expiresAt,
        ];

        return $this->jwt->encode($payload, $key, 'HS256');
    }

    /**
     * Get Country Name
     *
     * @param string $countryCode
     * @return string
     */
    private function getCountryName(string $countryCode): string
    {
        return $this->countryFactory->create()->loadByCode($countryCode)->getName();
    }
}
