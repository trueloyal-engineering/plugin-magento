<?php

namespace TrueLoyal\LoyaltyRewards\Helper;

use Exception;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\ImageFactory;
use Magento\Catalog\Model\ProductCategoryList;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\SessionFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;
use Magento\Customer\Model\Customer as CustomerModel;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
use Magento\Framework\View\Asset\Repository;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use TrueLoyal\LoyaltyRewards\Logger\Logger as TrueLoyalLogger;
use TrueLoyal\LoyaltyRewards\Model\Client\EventPublisher;
use TrueLoyal\LoyaltyRewards\Model\Client\HttpClient;
use TrueLoyal\LoyaltyRewards\Model\Client\LiveApi;
use TrueLoyal\LoyaltyRewards\Model\Client\ResponseParser;
use TrueLoyal\LoyaltyRewards\Model\MemberIdResolver;
use TrueLoyal\LoyaltyRewards\Model\Settings;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalEavAttributeFactory;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalQuoteFactory;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalQuoteItemFactory;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalReviewFactory;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalSalesOrderFactory;

class Config extends AbstractHelper
{
    public const XML_PATH_LOYALTY_REWARDS_ACTIVE = Settings::XML_PATH_LOYALTY_REWARDS_ACTIVE;
    public const XML_PATH_DASHBOARD_HIDDEN_FOR_GUESTS = Settings::XML_PATH_DASHBOARD_HIDDEN_FOR_GUESTS;
    public const XML_PATH_WEB_HOOK_URL = Settings::XML_PATH_WEB_HOOK_URL;
    public const XML_PATH_WEBHOOK_INTEGRATION_ID = Settings::XML_PATH_WEBHOOK_INTEGRATION_ID;
    public const XML_PATH_WEBHOOK_INTEGRATION_URL = Settings::XML_PATH_WEBHOOK_INTEGRATION_URL;
    /* Integration on the "Magento v2 -> TrueLoyal" ZIF connector; older builds created magento_to_zinrelo (v1) */
    public const INTEGRATION_TYPE = 'magento_to_trueloyal';
    public const XML_PATH_LIVE_WEB_HOOK_URL = Settings::XML_PATH_LIVE_WEB_HOOK_URL;
    public const XML_PATH_ABANDONED_CART_TIME = Settings::XML_PATH_ABANDONED_CART_TIME;
    public const XML_PATH_PARTNER_ID = Settings::XML_PATH_PARTNER_ID;
    public const XML_PATH_API_KEY = Settings::XML_PATH_API_KEY;
    public const XML_PATH_API_KEY_IDENTIFIER = Settings::XML_PATH_API_KEY_IDENTIFIER;
    public const XML_PATH_REWARD_EVENTS = Settings::XML_PATH_REWARD_EVENTS;
    public const XML_PATH_REWARDS_DROPDOWN_ACTIVE = Settings::XML_PATH_REWARDS_DROPDOWN_ACTIVE;
    public const XML_PATH_REWARDS_POINTS_AT_PDP = Settings::XML_PATH_REWARDS_POINTS_AT_PDP;
    public const XML_PATH_FREE_SHIPPING_LABEL = Settings::XML_PATH_FREE_SHIPPING_LABEL;
    public const XML_PATH_PRODUCT_PAGE_REWARD_LABEL = Settings::XML_PATH_PRODUCT_PAGE_REWARD_LABEL;
    public const XML_PATH_PDP_CART_PAGE = Settings::XML_PATH_PDP_CART_PAGE;
    public const XML_PATH_PDP_CART_PAGE_REWARD_LABEL = Settings::XML_PATH_PDP_CART_PAGE_REWARD_LABEL;
    public const XML_PATH_CART_PAGE_REWARD_DROPDOWN_LABEL = Settings::XML_PATH_CART_PAGE_REWARD_DROPDOWN_LABEL;
    public const XML_PATH_LANGUAGES = Settings::XML_PATH_LANGUAGES;
    public const XML_PATH_AUTO_ENROLLMENT = Settings::XML_PATH_AUTO_ENROLLMENT;
    public const XML_PATH_OPT_IN_FIELD_NAME = Settings::XML_PATH_OPT_IN_FIELD_NAME;
    public const XML_PATH_MEMBER_IDENTIFIER_PREFIX = Settings::XML_PATH_MEMBER_IDENTIFIER_PREFIX;
    public const XML_PATH_MEMBER_IDENTIFIER = Settings::XML_PATH_MEMBER_IDENTIFIER;
    public const XML_PATH_CUSTOM_MEMBER_STORE_CODE = Settings::XML_PATH_CUSTOM_MEMBER_STORE_CODE;
    public const XML_PATH_CUSTOM_MEMBER_STORE_ID = Settings::XML_PATH_CUSTOM_MEMBER_STORE_ID;
    public const XML_PATH_CUSTOM_MEMBER_STORE_CURRENCY = Settings::XML_PATH_CUSTOM_MEMBER_STORE_CURRENCY;
    public const XML_PATH_PREFERRED_LANGUAGE = Settings::XML_PATH_PREFERRED_LANGUAGE;

    /**
     * Cookie life time
     */
    public const COOKIE_LIFE = 300;
    /**
     * Name of Cookie that holds private content version
     */
    public const COOKIE_NAME = 'trueloyal';
    /**
     * Outbound call limits in seconds (Magento's Curl default is 300 s, which would hang a checkout or cart page)
     */
    public const HTTP_CONNECT_TIMEOUT = HttpClient::CONNECT_TIMEOUT;
    public const HTTP_TIMEOUT = HttpClient::TIMEOUT;
    public const HTTP_INTEGRATION_TIMEOUT = 30;

    /**
     * Available points per member, fetched once per request (the cart page needs them several times)
     *
     * @var array
     */
    private $rewardPointsByMember = [];

    /**
     * @var Json
     */
    public $json;
    /**
     * @var TrueLoyalLogger
     */
    public $logger;
    /**
     * @var CartRepositoryInterface
     */
    public $quoteRepository;
    /**
     * @var Session
     */
    protected $customerSession;
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;
    /**
     * @var TimezoneInterface
     */
    protected $timezoneInterface;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var ProductCategoryList
     */
    protected $productCategory;
    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;
    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;
    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;
    /**
     * @var RequestInterface
     */
    protected $request;
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;
    /**
     * @var Repository
     */
    protected $assetRepos;
    /**
     * @var ImageFactory
     */
    protected $helperImageFactory;
    /**
     * @var Escaper
     */
    protected $escaper;
    /**
     * @var TrueLoyalEavAttributeFactory
     */
    public $trueloyalEavAttributeFactory;
    /**
     * @var TrueLoyalQuoteFactory
     */
    public $trueloyalQuoteFactory;
    /**
     * @var TrueLoyalQuoteItemFactory
     */
    public $trueloyalQuoteItemFactory;
    /**
     * @var TrueLoyalReviewFactory
     */
    public $trueloyalReviewFactory;
    /**
     * @var TrueLoyalSalesOrderFactory
     */
    public $trueloyalSalesOrderFactory;
    /**
     * @var Attribute
     */
    public $eavAttribute;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param TrueLoyalLogger $logger
     * @param RequestInterface $request
     * @param ProductCategoryList $productCategory
     * @param CustomerRepositoryInterface $customerRepository
     * @param TimezoneInterface $timezoneInterface
     * @param ProductRepositoryInterface $productRepository
     * @param SessionFactory $customerSession
     * @param CheckoutSession $checkoutSession
     * @param QuoteFactory $quoteFactory
     * @param Repository $assetRepos
     * @param Escaper $escaper
     * @param ImageFactory $helperImageFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param StoreManagerInterface $storeManager
     * @param Json $json
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param SessionManagerInterface $sessionManager
     * @param TrueLoyalEavAttributeFactory $trueloyalEavAttributeFactory
     * @param TrueLoyalQuoteFactory $trueloyalQuoteFactory
     * @param TrueLoyalQuoteItemFactory $trueloyalQuoteItemFactory
     * @param TrueLoyalReviewFactory $trueloyalReviewFactory
     * @param TrueLoyalSalesOrderFactory $trueloyalSalesOrderFactory
     * @param Attribute $eavAttribute
     * @param Settings $settings
     * @param MemberIdResolver $memberIdResolver
     * @param LiveApi $liveApi
     * @param EventPublisher $eventPublisher
     * @param HttpClient $httpClient
     * @param ResponseParser $responseParser
     */
    public function __construct(
        Context $context,
        TrueLoyalLogger $logger,
        RequestInterface $request,
        ProductCategoryList $productCategory,
        CustomerRepositoryInterface $customerRepository,
        TimezoneInterface $timezoneInterface,
        ProductRepositoryInterface $productRepository,
        SessionFactory $customerSession,
        CheckoutSession $checkoutSession,
        QuoteFactory $quoteFactory,
        Repository $assetRepos,
        Escaper $escaper,
        ImageFactory $helperImageFactory,
        OrderRepositoryInterface $orderRepository,
        CategoryRepositoryInterface $categoryRepository,
        StoreManagerInterface $storeManager,
        Json $json,
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly SessionManagerInterface $sessionManager,
        CartRepositoryInterface $quoteRepository,
        TrueLoyalEavAttributeFactory $trueloyalEavAttributeFactory,
        TrueLoyalQuoteFactory $trueloyalQuoteFactory,
        TrueLoyalQuoteItemFactory $trueloyalQuoteItemFactory,
        TrueLoyalReviewFactory $trueloyalReviewFactory,
        TrueLoyalSalesOrderFactory $trueloyalSalesOrderFactory,
        Attribute $eavAttribute,
        protected readonly Settings $settings,
        protected readonly MemberIdResolver $memberIdResolver,
        protected readonly LiveApi $liveApi,
        private readonly EventPublisher $eventPublisher,
        private readonly HttpClient $httpClient,
        private readonly ResponseParser $responseParser
    ) {
        $this->escaper = $escaper;
        $this->request = $request;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->quoteFactory = $quoteFactory;
        $this->customerRepository = $customerRepository;
        $this->categoryRepository = $categoryRepository;
        $this->productCategory = $productCategory;
        $this->timezoneInterface = $timezoneInterface;
        $this->productRepository = $productRepository;
        $this->customerSession = $customerSession;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->json = $json;
        $this->helperImageFactory = $helperImageFactory;
        $this->assetRepos = $assetRepos;
        $this->trueloyalEavAttributeFactory = $trueloyalEavAttributeFactory;
        $this->trueloyalQuoteFactory = $trueloyalQuoteFactory;
        $this->trueloyalQuoteItemFactory = $trueloyalQuoteItemFactory;
        $this->trueloyalReviewFactory = $trueloyalReviewFactory;
        $this->trueloyalSalesOrderFactory = $trueloyalSalesOrderFactory;
        $this->quoteRepository = $quoteRepository;
        $this->eavAttribute = $eavAttribute;
        parent::__construct($context);
    }

    /**
     * Get Config Languages
     *
     * @return mixed
     */
    public function getConfigLanguage()
    {
        return $this->getConfig(self::XML_PATH_LANGUAGES);
    }

    public function getPreferredLanguage(): string
    {
        return $this->getConfig(self::XML_PATH_PREFERRED_LANGUAGE) ?? '';
    }

    /**
     * Get Config
     *
     * @param mixed $config_path
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getConfig($config_path)
    {
        return $this->settings->getValue($config_path);
    }

    /**
     * Save Config
     *
     * @param string $config_path
     * @param mixed $config_value
     * @return void
     */
    public function saveConfig($config_path, $config_value)
    {
        $this->settings->save($config_path, $config_value === null ? null : (string)$config_value);
    }
    
    /**
     * Web Hook Url, which is received from TrueLoyal to sent API resquest
     *
     * @return mixed
     */
    public function getWebHookUrl()
    {
        return $this->getConfig(self::XML_PATH_WEB_HOOK_URL);
    }

    /**
     * Save Web Hook Url
     *
     * @return mixed
     */
    public function saveWebHookUrl($webhookUrl)
    {
        $this->saveConfig(self::XML_PATH_WEB_HOOK_URL, $webhookUrl);
        return true;
    }

    /**
     * Create or update the TrueLoyal integration (the ZIF workflow that receives the events)
     *
     * @param string $url
     * @return array the integration: id, integration_type, status, config.zif_config.workflow_url
     * @throws Exception when TrueLoyal refuses the request
     */
    public function createOrUpdateZIFIntegration($url)
    {
        $headers = [
            "content-type" => "application/json",
            "accept" => "application/json",
            'api-key' => $this->getApiKey(),
            "partner-id" => $this->getPartnerId()
        ];
        $events = $this->getRewardEvents();
        if (in_array('order_refund', $events)) {
                $events = array_diff($events, ['order_refund']);
                $events[] = 'partial_order_refund';
                $events[] = 'full_order_refund';
        }

        $body = [
            "integration_type" => self::INTEGRATION_TYPE,
            "config" => [
                "secret_key" => $this->getApiKey(),
                "events" => array_values($events)
            ],
            "status" => "active"
        ];
        $response = $this->httpClient->send('post', $url, $headers, json_encode($body), self::HTTP_INTEGRATION_TIMEOUT);
        $responseCode = $response['status'];
        $data = json_decode($response['body'], true);
        if ($responseCode === 200 && !empty($data['success'])
            && !empty($data['data']['config']['zif_config']['workflow_url'])) {
            return $data['data'];
        }
        $this->saveWebHookUrl(NULL);
        $this->saveWebHookIntegrationID(NULL);
        $error = 'Failed to create a Webhook URL: '
            . rtrim($data['reason'] ?? $data['message'] ?? ('HTTP ' . $responseCode), '.');
        $errorCode = $data['error_code'] ?? '';
        if ($errorCode) {
            $error .= ' (' . $errorCode . ')';
        }
        if (in_array($responseCode, [401, 403]) || stripos($errorCode, 'UNAUTHORIZED') !== false) {
            $error .= '. Check that the Partner ID and the API Key are correct, and that the API Key'
                . ' isn\'t swapped with the API Key Identifier';
        }
        throw new Exception($error . '.');
    }


    /**
     * Get Web Hook Integration ID
     *
     * @return mixed
     */
    public function getWebHookIntegrationID()
    {
        return $this->getConfig(self::XML_PATH_WEBHOOK_INTEGRATION_ID);
    }

    /**
     * Save Web Hook Integration ID
     *
     * @return mixed
     */
    public function saveWebHookIntegrationID($webhookIntegrationID)
    {
        $this->saveConfig(self::XML_PATH_WEBHOOK_INTEGRATION_ID, $webhookIntegrationID);
    }

    /**
     * Get Web Hook Integration URL
     *
     * @return mixed
     */
    public function getWebHookIntegrationURL()
    {
        return $this->getConfig(self::XML_PATH_WEBHOOK_INTEGRATION_URL);
    }

    /**
     * Is Json
     *
     * @param string $string
     * @return bool
     */
    public function isJson($string)
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Json Serialize
     *
     * @param mixed $data
     * @return bool|false|string
     */
    public function jsonSerialize($data)
    {
        try {
            return $this->json->serialize($data);
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }

    /**
     * Json UnSerialize
     *
     * @param mixed $data
     * @return bool|false|string|array
     */
    public function jsonUnSerialize($data)
    {
        try {
            return $this->json->unserialize($data);
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }

    /**
     * Check Is Reward DropDown Enable
     *
     * @return bool
     */
    public function isRewardDropDownEnable()
    {
        $isModuleEnable = $this->isModuleEnabled();
        $isRewardDropDownEnable = $this->getConfig(self::XML_PATH_REWARDS_DROPDOWN_ACTIVE) ?? false;
        return ($isRewardDropDownEnable && $isModuleEnable) ?? false;
    }

    /**
     * Check module is enabled or disabled
     *
     * @return bool
     */
    public function isModuleEnabled()
    {
        if($this->getConfig(self::XML_PATH_LOYALTY_REWARDS_ACTIVE)) {
            if(!$this->isAutoEnrollmentEnabled()) {
                $customerId = $this->customerSession->create()->getCustomerId();
                if ($customerId) {
                    $trueloyalOptedIn = $this->getOptInCustomAttributeValue($customerId);
                    if (!$trueloyalOptedIn) {
                        return false;
                    }
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Check auto enrollment is enabled or disabled
     *
     * @return bool
     */
    public function isAutoEnrollmentEnabled()
    {
        return $this->getConfig(self::XML_PATH_AUTO_ENROLLMENT) ? true : false;
    }

    /**
     * Get Opt In Field Name
     *
     * @return mixed
     */
    public function getOptInAttributeCode()
    {
        return $this->getConfig(self::XML_PATH_OPT_IN_FIELD_NAME) ?? '';
    }

    /**
     * Check dashboar is enabled or disabled for guests
     *
     * @return bool
     */
    public function isDashboardHiddenForGuests()
    {
        return $this->getConfig(self::XML_PATH_DASHBOARD_HIDDEN_FOR_GUESTS) ? true : false;
    }

    /**
     * Check Reward Point can show at PDP or not
     *
     * @return bool
     */
    public function isRewardPointAtPdpEnabled()
    {
        $isModuleEnable = $this->isModuleEnabled();
        $isRewardPointAtPdpEnabled = $this->getConfig(self::XML_PATH_REWARDS_POINTS_AT_PDP) ?? false;
        $isDashboardHiddenForGuests = $this->isDashboardHiddenForGuests();
        if($isDashboardHiddenForGuests && !$this->getMemberIdentifierValue()) {
            return false;
        }
        return ($isRewardPointAtPdpEnabled && $isModuleEnable) ?? false;
    }

    /**
     * Get Label for show at Product view  Pages
     *
     * @return string
     */
    public function getRewardLabelAtProductPage(): string
    {
        return $this->getConfig(self::XML_PATH_PRODUCT_PAGE_REWARD_LABEL);
    }

     /**
     * Check Reward Points can show at Cart Page
     *
     * @return bool
     */
    public function isPDPAtCartEnabled()
    {
        $isModuleEnable = $this->isModuleEnabled();
        $isRewardPointAtCartEnabled = $this->getConfig(self::XML_PATH_PDP_CART_PAGE) ?? false;
        $isDashboardHiddenForGuests = $this->isDashboardHiddenForGuests();
        if($isDashboardHiddenForGuests && !$this->getMemberIdentifierValue()) {
            return false;
        }
        return ($isRewardPointAtCartEnabled && $isModuleEnable) ?? false;
    }

    /**
     * Get Label for show at Cart Pages
     *
     * @return string
     */
    public function getPDPLabelAtCartPage(): string
    {
        return $this->getConfig(self::XML_PATH_PDP_CART_PAGE_REWARD_LABEL);
    }

    /**
     * Get Label for show at cart page reward list dropdown section
     *
     * @return string
     */
    public function getRewardLabelAtCartPage(): string
    {
        return $this->getConfig(self::XML_PATH_CART_PAGE_REWARD_DROPDOWN_LABEL);
    }

    /**
     * Get Free Shipping Label
     *
     * @return mixed
     */
    public function getFreeShippingLabel()
    {
        return $this->getConfig(self::XML_PATH_FREE_SHIPPING_LABEL);
    }

    /**
     * Get Abandoned Cart Time
     *
     * @return mixed
     */
    public function getAbandonedCartTime()
    {
        return $this->getConfig(self::XML_PATH_ABANDONED_CART_TIME);
    }

    /**
     * Get Reward Events
     *
     * @return array
     */
    public function getRewardEvents()
    {
        if (!empty($this->getConfig(self::XML_PATH_REWARD_EVENTS)) && $this->isModuleEnabled()) {
            return explode(',', $this->getConfig(self::XML_PATH_REWARD_EVENTS));
        }
        return [];
    }

    /**
     * Get Customer Email By Checkout
     *
     * @return string
     */
    public function getCustomerEmailByCheckout()
    {
        return $this->checkoutSession->getCustomer()->getEmail() ?? '';
    }

    /**
     * Get applied reward rule name
     *
     * @param Quote $quote
     * @return string
     */
    public function getRewardAppliedRuleLabel($quote): string
    {
        $rewardData = $this->getRewardRulesData($quote);
        return $this->escaper->escapeHtml($rewardData['reward_name']);
    }

    /**
     * Get Applied Rule Data
     *
     * @param mixed $quote
     * @param string $ruleId
     * @return mixed
     */
    public function getRewardRulesData($quote, $ruleId = "")
    {
        $trueloyalQuote = $this->getTrueLoyalQuoteByQuoteId($quote->getId());
        if ($ruleId == "" && $trueloyalQuote) {
            $ruleId = !$trueloyalQuote->isEmpty() ? $trueloyalQuote->getRedeemRewardDiscount() : '';
        }

        if (!$trueloyalQuote->isEmpty()) {
            if ($trueloyalQuote->getRewardRulesData()) {
                $rewardRules = $this->json->unserialize($trueloyalQuote->getRewardRulesData());
                return $rewardRules[(string)$ruleId] ?? [];
            }
        }
        return [];
    }

    /**
     * Get reward point rules
     *
     * @return array
     */
    public function getRedeemRules()
    {
        $memberId = $this->getMemberIdentifierValue();
        $url = $this->getLiveWebHookUrl() . "members/" . $memberId . "/rewards";
        $url = $this->getIdParam($url);
        $response = $this->request($url, "", "get", "live_api");
        $rewardRules = [];
        if ($response && $response["success"] && !empty($response["result"]["data"]["rewards"])) {
            $rules = $response["result"]["data"]["rewards"];
            $rewardTypes = $this->getDefaultRewardTypes();
            $availablePoint = $this->getRewardPoints();
            $quote = $this->getQuote();
            $subTotal = $quote->getSubtotal();
            foreach ($rules as $rule) {
                if (($rule['reward_sub_type'] == 'Fixed Amount Discount' && $rule['reward_value'] > $subTotal) ||
                    $availablePoint < $rule['points_to_be_redeemed']
                ) {
                    continue;
                }
                if (in_array($rule["reward_sub_type"], array_values($rewardTypes), true)) {
                    $maximumRedemption = !empty($rule["extra_parameters"]["maximum_redemption_limit"])
                        ? $rule["extra_parameters"]["maximum_redemption_limit"] : $availablePoint;
                    $minimumRedemption = !empty($rule["extra_parameters"]["minimum_redemption_limit"])
                        ? $rule["extra_parameters"]["minimum_redemption_limit"] : "";
                    /*A member can't redeem more points than their balance*/
                    if (is_numeric($availablePoint)) {
                        $maximumRedemption = min($maximumRedemption, $availablePoint);
                        if ($minimumRedemption !== "" && $minimumRedemption > $maximumRedemption) {
                            continue;
                        }
                    }
                    foreach ($rewardTypes as $key => $value) {
                        if ($rule["reward_sub_type"] == $value) {
                            $rewardRules[$rule["reward_id"]] = [
                                "rule" => $key,
                                "reward_id" => $rule["reward_id"],
                                "id" => '',
                                "reward_name" => $rule["reward_name"],
                                "reward_value" => !empty($rule["reward_value"]) ? $rule["reward_value"] : "",
                                "maximum_redemption_limit" => $maximumRedemption,
                                "minimum_redemption_limit" => $minimumRedemption,
                                "conversion_rate" => !empty($rule["extra_parameters"]["conversion_rate"])
                                    ? $rule["extra_parameters"]["conversion_rate"] : "",
                                "product_id" => $rule["product_id"] ?? ""
                            ];
                        }
                    }
                }
            }
        }
        return $rewardRules;
    }

    /**
     * Get Customer Email By Session
     *
     * @return string
     */
    public function getCustomerEmailBySession()
    {
        return $this->customerSession->create()->getCustomer()->getEmail() ?? '';
    }

    /**
     * Get configured member identifier prefix
     *
     * @return string
     */
    public function getMemberIdentifierPrefix(): string
    {
        return $this->getConfig(self::XML_PATH_MEMBER_IDENTIFIER_PREFIX) ?? '';
    }
        
    /**
     * Get configured member identifier type ('member_id' or 'member_email')
     *
     * @return string
     */
    public function getMemberIdentifier(): string
    {
        return $this->getConfig(self::XML_PATH_MEMBER_IDENTIFIER) ?? 'member_email';
    }

    /**
     * Get Custom Member Attribute: Store ID
     *
     * @return string
     */
    public function getCustomMemberStoreCode(): string
    {
        return $this->getConfig(self::XML_PATH_CUSTOM_MEMBER_STORE_CODE) ?? '';
    }

    public function getCustomMemberStoreId(): string
    {
        return $this->getConfig(self::XML_PATH_CUSTOM_MEMBER_STORE_ID) ?? '';
    }

    /**
     * Get Custom Member Attribute: Store Currency
     *
     * @return string
     */
    public function getCustomMemberStoreCurrency(): string
    {
        return $this->getConfig(self::XML_PATH_CUSTOM_MEMBER_STORE_CURRENCY) ?? '';
    }

    /**
     * Get current store currency code
     *
     * @return string
     */
    public function getStoreCurrencyCode(): string
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode() ?? '';
    }

    /**
     * Get the member identifier value based on the configured type.
     * Returns str_pad formatted customer ID for member_id, or customer email for member_email.
     *
     * @return string
     */
    public function getMemberIdentifierValue(): string
    {
        return $this->memberIdResolver->forSession();
    }

    /**
     * Get Customer Custom Attribute Value
     *
     * @return mixed
     */
    public function getOptInCustomAttributeValue($customerId)
    {
        $customerData = $this->customerRepository->getById($customerId);
        $opt_in_attribute = $this->getOptInAttributeCode();
        $trueloyalOptedIn = $customerData->getCustomAttribute($opt_in_attribute);
        if ($trueloyalOptedIn) {
            return $trueloyalOptedIn->getValue();
        }
        return null;
    }


    /**
     * Get Free Product
     *
     * @return mixed
     */
    public function getFreeProduct()
    {
        $quote = $this->checkoutSession->getQuote();
        $rewardData = $this->getRewardRulesData($quote);
        if ($rewardData) {
            return $rewardData["product_id"];
        }
        return '';
    }

    /**
     * Get Web Hook Url
     *
     * @return mixed
     */
    public function getLiveWebHookUrl()
    {
        return $this->getConfig(self::XML_PATH_LIVE_WEB_HOOK_URL);
    }

    /**
     * Get idParam
     *
     * @param string $url
     * @return string
     */
    public function getIdParam($url): string
    {
        $params = [
            "idParam" => "member_id"
        ];
        $url .= "?" . http_build_query($params);
        return $url;
    }

    /**
     * Request to trueloyal for specific event URL
     *
     * @param mixed $url
     * @param mixed $params
     * @param string $requestType
     * @param string $apiType
     * @return mixed
     */
    public function request($url, $params, $requestType = "post", $apiType = "event_api")
    {
        try {
            if ($apiType == "live_api") {
                return $this->liveApi->request($requestType == "post" ? "post" : "get", (string)$url, (string)$params);
            }
            return $this->eventPublisher->send((string)$url, (string)$params);
        } catch (Exception $e) {
            $this->logger->addErrorLog($e->getMessage());
        }
    }

    /**
     * Decode a TrueLoyal response: success false when the body has an error_code
     *
     * @param mixed $responseData
     * @return array{success: bool, result: mixed}
     */
    public function returnResponseData($responseData): array
    {
        return $this->responseParser->parse((string)$responseData);
    }

    /**
     * Get Api Key
     *
     * @return mixed
     */
    public function getApiKey()
    {
        return $this->getConfig(self::XML_PATH_API_KEY) ?? '';
    }

    /**
     * Get Api Key Identifier
     *
     * @return mixed
     */
    public function getApiKeyIdentifier()
    {
        return $this->getConfig(self::XML_PATH_API_KEY_IDENTIFIER) ?? '';
    }

    /**
     * Get Partner Id
     *
     * @return mixed
     */
    public function getPartnerId()
    {
        return $this->getConfig(self::XML_PATH_PARTNER_ID) ?? '';
    }

    /**
     * Generate hash hmac key
     *
     * @param string $shaMethod
     * @param string $msgData
     * @param string $secret_key
     * @return mixed
     */
    public function generateHasHmac($shaMethod, $msgData, $secret_key)
    {
        return hash_hmac($shaMethod, $msgData, $secret_key);
    }

    /**
     * Get default reward to apply for redeem
     *
     * @return array
     */
    public function getDefaultRewardTypes(): array
    {
        return [
            "product_redemption" => "Product Redemption",
            "fixed_amount_discount" => "Fixed Amount Discount",
            "percentage_discount" => "Percentage Discount",
            "free_shipping" => "Free Shipping",
            "flexible_points_reward" => "Flexible Points Reward"
        ];
    }

    /**
     * Get available reward points
     *
     * @return mixed
     */
    public function getRewardPoints()
    {
        $memberId = $this->getMemberIdentifierValue();
        if (!array_key_exists($memberId, $this->rewardPointsByMember)) {
            $this->rewardPointsByMember[$memberId] = $this->fetchRewardPoints($memberId);
        }
        return $this->rewardPointsByMember[$memberId];
    }

    /**
     * Get available reward points from TrueLoyal
     *
     * @param string $memberId
     * @return mixed
     */
    private function fetchRewardPoints(string $memberId)
    {
        try {
            $url = $this->getLiveWebHookUrl() . "members/" . $memberId;
            $url = $this->getIdParam($url);
            $response = $this->request($url, "", "get", "live_api");
            if ($response) {
                if ($response["success"] && !empty($response["result"]["data"]["available_points"])) {
                    $point = $response["result"]["data"]["available_points"];
                    return $point > 0 ? $point : "error";
                } else {
                    return "error";
                }
            }
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
            return "error";
        }
    }

    /**
     * Get quote
     *
     * @return CartInterface|Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuote()
    {
        return $this->checkoutSession->getQuote();
    }

    /**
     * Get data from cookie set in remote address
     *
     * @param mixed $name
     * @return value
     */
    public function getCookie($name)
    {
        return $this->cookieManager->getCookie($name);
    }

    /**
     * Set data to cookie in remote address
     *
     * @param mixed $value
     * @param int $duration
     * @throws CookieSizeLimitReachedException
     * @throws FailureToSendException
     * @throws InputException
     */
    public function setCookie($value, $duration = 300)
    {
        $metadata = $this->cookieMetadataFactory
            ->createPublicCookieMetadata()
            ->setDuration($duration)
            ->setPath($this->sessionManager->getCookiePath())
            ->setDomain($this->sessionManager->getCookieDomain());

        $this->cookieManager->setPublicCookie(self::COOKIE_NAME, $value, $metadata);
    }

    /**
     * Delete cookie remote address
     *
     * @return void
     * @throws FailureToSendException
     * @throws InputException
     */
    public function deleteCookie()
    {
        $this->cookieManager->deleteCookie(
            self::COOKIE_NAME,
            $this->cookieMetadataFactory
                ->createCookieMetadata()
                ->setPath($this->sessionManager->getCookiePath())
                ->setDomain($this->sessionManager->getCookieDomain())
        );
    }

    /*Managed to set trueloyal quote, quoteItem, review, sales related data to custom table*/

    /**
     * Get TrueLoyal quote specific Data using QuoteId
     *
     * @param int $quoteId
     */
    public function getTrueLoyalQuoteByQuoteId($quoteId)
    {
        return $this->trueloyalQuoteFactory->create()
            ->getCollection()
            ->addFieldToFilter('quote_id', $quoteId)
            ->getFirstItem();
    }

    /**
     * Get TrueLoyal quote item specific Data using itemId
     *
     * @param int $itemId
     */
    public function getTrueLoyalQuoteItemByItemId($itemId)
    {
        return $this->trueloyalQuoteItemFactory->create()
            ->getCollection()
            ->addFieldToFilter('quote_item_id', $itemId)
            ->getFirstItem();
    }

    /**
     * Get the IDs of the given quote items that are redeemed free products
     *
     * @param int[] $itemIds
     * @return int[]
     */
    public function getTrueLoyalFreeProductItemIds(array $itemIds)
    {
        if (empty($itemIds)) {
            return [];
        }
        return $this->trueloyalQuoteItemFactory->create()
            ->getCollection()
            ->addFieldToFilter('quote_item_id', ['in' => $itemIds])
            ->addFieldToFilter('is_trueloyal_free_product', 1)
            ->getColumnValues('quote_item_id');
    }

    /**
     * Set trueloyal abandoned cart sent status
     *
     * @param int $quoteId
     * @param int $value
     */
    public function setAbandonedCartSent($quoteId, $value)
    {
        $trueloyalQuote = $this->getTrueLoyalQuoteByQuoteId($quoteId);
        if (!$trueloyalQuote->isEmpty()) {
            $trueloyalQuote->setIsAbandonedCartSent($value)->save();
        } else {
            $trueloyalQuote->setIsAbandonedCartSent($value);
            $trueloyalQuote->setQuoteId($quoteId);
            $trueloyalQuote->save();
        }
    }

    /**
     * Get TrueLoyal Order item specific Data using orderID
     *
     * @param int $orderId
     */
    public function getTrueLoyalOrderByOrderId($orderId)
    {
        return $this->trueloyalSalesOrderFactory->create()
            ->getCollection()
            ->addFieldToFilter('order_id', $orderId)
            ->getFirstItem();
    }

    /**
     * Get TrueLoyal product review using reviewId
     *
     * @param int $reviewId
     */
    public function getTrueLoyalReviewByReviewId($reviewId)
    {
        return $this->trueloyalReviewFactory->create()
            ->getCollection()
            ->addFieldToFilter('review_id', $reviewId)
            ->getFirstItem();
    }

    /**
     * Get TrueLoyal attribute related data using attribute Id
     *
     * @param int $attributeId
     */
    public function getTrueLoyalAttributeByAttributeId($attributeId)
    {
        return $this->trueloyalEavAttributeFactory->create()
            ->getCollection()
            ->addFieldToFilter('attribute_id', $attributeId)
            ->getFirstItem();
    }

    /**
     * Get customer attribute Id using attribute code
     *
     * @param string $attributeCode
     */
    public function getCustomerAttributeId($attributeCode)
    {
        return $this->eavAttribute->getIdByCode(CustomerModel::ENTITY, $attributeCode);
    }
}
