<?php

namespace TrueLoyal\LoyaltyRewards\Model;

use Firebase\JWT\JWT;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\SessionFactory;
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
        Resolver $store,
        SerializerInterface $serializer,
        StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->jwt = $jwt;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->sessionFactory = $sessionFactory;
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
        $address = null;
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
            $address = $customer->getDefaultShippingAddress() ?: $customer->getDefaultBillingAddress();
            $customerEmail = $customer->getEmail();
            $customerFirstName = $customer->getFirstname();
            $customerLastName = $customer->getLastname();
            $customerBirthDate = $customer->getDob();
            $pattern = '/(\d{4})-(\d{2})-(\d{2})/';
            if ($customerBirthDate && preg_match($pattern, $customerBirthDate)) {
                $customerBirthDate = preg_replace($pattern, '$2/$3/$1', $customerBirthDate);
            }
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
            'preferred_language' => $lang,
            'custom_attributes'  => $customAttributes,
            'exp'                => $expiresAt,
        ];

        /* The widget login writes these to the member, so they match the TrueLoyal customer sync (webhook) and are
           left out when empty instead of blanking what the sync stored. */
        if ($customerBirthDate) {
            $payload['birthdate'] = $customerBirthDate;
        }
        if ($address) {
            $street = (array)$address->getStreet();
            $payload['address'] = [
                'line1'       => (string)($street[0] ?? ""),
                'line2'       => implode(', ', array_slice($street, 1)),
                'city'        => (string)$address->getCity(),
                'state'       => (string)$address->getRegion(),
                'country'     => (string)$address->getCountryId(),
                'postal_code' => (string)$address->getPostcode(),
            ];
            $telephone = trim((string)$address->getTelephone());
            if (preg_match('/^\+[0-9]{1,3}-[0-9]+$/', $telephone)) {
                $payload['phone_number'] = $telephone;
            }
        }

        return $this->jwt->encode($payload, $key, 'HS256');
    }
}
