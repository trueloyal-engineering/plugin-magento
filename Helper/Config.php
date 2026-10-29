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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
use Magento\Framework\View\Asset\Repository;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use TrueLoyal\LoyaltyRewards\Logger\Logger as TrueLoyalLogger;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalEavAttributeFactory;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalQuoteFactory;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalQuoteItemFactory;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalReviewFactory;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalSalesOrderFactory;

class Config extends AbstractHelper
{
    public const XML_PATH_LOYALTY_REWARDS_ACTIVE = "trueloyal_loyaltyRewards/settings/loyalty_rewards_active";
    public const XML_PATH_DASHBOARD_HIDDEN_FOR_GUESTS = "trueloyal_loyaltyRewards/settings/hide_for_guests";
    public const XML_PATH_WEB_HOOK_URL = "trueloyal_loyaltyRewards/settings/web_hook_url";
    public const XML_PATH_WEBHOOK_INTEGRATION_ID = 'trueloyal_loyaltyRewards/settings/webhook_integration_id';
    public const XML_PATH_WEBHOOK_INTEGRATION_URL = 'trueloyal_loyaltyRewards/settings/webhook_integration_url';
    public const XML_PATH_LIVE_WEB_HOOK_URL = "trueloyal_loyaltyRewards/settings/live_web_hook_url";
    public const XML_PATH_ABANDONED_CART_TIME = "trueloyal_loyaltyRewards/settings/abandoned_cart_time";
    public const XML_PATH_PARTNER_ID = "trueloyal_loyaltyRewards/settings/partner_id";
    public const XML_PATH_API_KEY = "trueloyal_loyaltyRewards/settings/api_key";
    public const XML_PATH_API_KEY_IDENTIFIER = "trueloyal_loyaltyRewards/settings/api_key_identifier";
    public const XML_PATH_REWARD_EVENTS = "trueloyal_loyaltyRewards/settings/reward_events";
    public const XML_PATH_REWARDS_DROPDOWN_ACTIVE = "trueloyal_loyaltyRewards/settings/rewards_event_drop_down_active";
    public const XML_PATH_REWARDS_POINTS_AT_PDP = "trueloyal_loyaltyRewards/settings/product_page_rewards_point_enable";
    public const XML_PATH_FREE_SHIPPING_LABEL = "trueloyal_loyaltyRewards/settings/free_shipping_label";
    public const XML_PATH_PRODUCT_PAGE_REWARD_LABEL = "trueloyal_loyaltyRewards/settings/product_page_reward_label";
    public const XML_PATH_PDP_CART_PAGE = "trueloyal_loyaltyRewards/settings/cart_page_rewards_point_enable";
    public const XML_PATH_PDP_CART_PAGE_REWARD_LABEL = "trueloyal_loyaltyRewards/settings/cart_page_reward_label";
    public const XML_PATH_CART_PAGE_REWARD_DROPDOWN_LABEL =
        "trueloyal_loyaltyRewards/settings/cart_page_reward_dropdown_label";
    public const XML_PATH_LANGUAGES = 'trueloyal_loyaltyRewards/settings/languages_mapping';
    public const XML_PATH_AUTO_ENROLLMENT = 'trueloyal_loyaltyRewards/settings/auto_enrollment';
    public const XML_PATH_OPT_IN_FIELD_NAME = 'trueloyal_loyaltyRewards/settings/opt_in_field_name';
    public const XML_PATH_MEMBER_IDENTIFIER_PREFIX = 'trueloyal_loyaltyRewards/settings/member_identifier_prefix';
    public const XML_PATH_MEMBER_IDENTIFIER = 'trueloyal_loyaltyRewards/settings/member_identifier';
    public const XML_PATH_CUSTOM_MEMBER_STORE_CODE = 'trueloyal_loyaltyRewards/settings/custom_member_attributes/store_code';
    public const XML_PATH_CUSTOM_MEMBER_STORE_ID = 'trueloyal_loyaltyRewards/settings/custom_member_attributes/store_id';
    public const XML_PATH_CUSTOM_MEMBER_STORE_CURRENCY = 'trueloyal_loyaltyRewards/settings/custom_member_attributes/store_currency';
    public const XML_PATH_PREFERRED_LANGUAGE = 'trueloyal_loyaltyRewards/settings/preferred_language';

    /**
     * Cookie life time
     */
    public const COOKIE_LIFE = 300;
    /**
     * Name of Cookie that holds private content version
     */
    public const COOKIE_NAME = 'trueloyal';

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
     * @var ScopeConfigInterface $scopeConfig
     */
    protected $scopeConfig;
    /**
     * @var WriterInterface $writeConfig
     */
    protected $writeConfig;
    /**
     * @var Session
     */
    protected $customerSession;
    /**
     * @var Curl
     */
    protected $curl;
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
     * @var CookieManagerInterface
     */
    private $cookieManager;
    /**
     * @var CookieMetadataFactory
     */
    private $cookieMetadataFactory;
    /**
     * @var SessionManagerInterface
     */
    private $sessionManager;
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
     * @param Curl $curl
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
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $writeConfig
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
     */
    public function __construct(
        Context $context,
        TrueLoyalLogger $logger,
        Curl $curl,
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
        ScopeConfigInterface $scopeConfig,
        WriterInterface $writeConfig,
        Json $json,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        SessionManagerInterface $sessionManager,
        CartRepositoryInterface $quoteRepository,
        TrueLoyalEavAttributeFactory $trueloyalEavAttributeFactory,
        TrueLoyalQuoteFactory $trueloyalQuoteFactory,
        TrueLoyalQuoteItemFactory $trueloyalQuoteItemFactory,
        TrueLoyalReviewFactory $trueloyalReviewFactory,
        TrueLoyalSalesOrderFactory $trueloyalSalesOrderFactory,
        Attribute $eavAttribute
    ) {
        $this->curl = $curl;
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
        $this->scopeConfig = $scopeConfig;
        $this->writeConfig = $writeConfig;
        $this->json = $json;
        $this->helperImageFactory = $helperImageFactory;
        $this->assetRepos = $assetRepos;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->sessionManager = $sessionManager;
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
        return $this->scopeConfig->getValue(
            $config_path,
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    /**
     * Save Config
     *
     * @return mixed
     */
    public function saveConfig($config_path, $config_value)
    {
        return $this->writeConfig->save(
            $config_path,
            $config_value
        );
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
     * Create ZIF Integration
     *
     * @return mixed
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
            "integration_type" => "magento_to_zinrelo",
            "config" => [
                "secret_key" => $this->getApiKey(),
                "events" => array_values($events)
            ],
            "status" => "active"
        ];
        $jsonBody = json_encode($body);
        $this->curl->setHeaders($headers);
        $this->curl->post($url, $jsonBody);
        $response = $this->curl->getBody();
        $responseCode = $this->curl->getStatus();
        $data = json_decode($response, true);
        $this->logger->info("==============Start==============");
        $this->logger->info("URL: " . $url);
        $this->logger->info("RequestType: " . "post");
        $this->logger->info("Headers: " . json_encode($headers));
        $this->logger->info("Response: " . $response);
        $this->logger->info("==============End===============");
        if ($responseCode === 200) {
            return $data;
        }
        else{
            $error = 'Failed to create a Webhook URL. Please check the configuration details and try again.';
            $this->saveWebHookUrl(NULL);
            $this->saveWebHookIntegrationID(NULL);
            throw new Exception($error);
        }
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
            return $this->json->serialize($data);
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
                return $rewardRules[$ruleId] ?? [];
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
                    foreach ($rewardTypes as $key => $value) {
                        if ($rule["reward_sub_type"] == $value) {
                            $rewardRules[$rule["reward_id"]] = [
                                "rule" => $key,
                                "reward_id" => $rule["reward_id"],
                                "id" => '',
                                "reward_name" => $rule["reward_name"],
                                "reward_value" => !empty($rule["reward_value"]) ? $rule["reward_value"] : "",
                                "maximum_redemption_limit" => !empty($rule["extra_parameters"]["maximum_redemption_limit"])
                                    ? $rule["extra_parameters"]["maximum_redemption_limit"] : $availablePoint,
                                "minimum_redemption_limit" => !empty($rule["extra_parameters"]["minimum_redemption_limit"])
                                    ? $rule["extra_parameters"]["minimum_redemption_limit"] : "",
                                "conversion_rate" => !empty($rule["extra_parameters"]["conversion_rate"])
                                    ? $rule["extra_parameters"]["conversion_rate"] : "",
                                "product_id" => isset($rule["product_id"]) ? $rule["product_id"] : ""
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
        $prefix = $this->getMemberIdentifierPrefix();
        if ($this->getMemberIdentifier() === 'member_id') {
            $value = $this->getFormattedMemberId($prefix);
            return $value ?? '';
        }
        $value = $this->getCustomerEmailBySession();
        return $value ? $prefix . $value : '';
    }

    /**
     * Get Formatted Member ID (ID as string with leading zeros)
     *
     * @return string
     */
    public function getFormattedMemberId($prefix = '')
    {
        $customerId = $this->customerSession->create()->getCustomerId();
        if (!$customerId) {
            return '';
        }
        $customerIdStr = (string)$customerId;
        if (strlen($prefix) + strlen($customerIdStr) < 3) {
            $padLength = max(0, 3 - strlen($prefix));
            return $prefix . str_pad($customerIdStr, $padLength, "0", STR_PAD_LEFT);
        }
        return $prefix . $customerIdStr;
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
            $message = '';
            /* Add request data to log file*/
            if ($apiType == "live_api") {
                $headers = [
                    "content-type" => "application/json",
                    "api-key" => $this->getApiKey(),
                    "partner-id" => $this->getPartnerId()
                ];
            } else {
                $milliseconds = round(microtime(true) * 1000);
                $nonce = $milliseconds;
                $request_body = $params;
                $secret_key = $this->getApiKey();
                $message = $request_body . ":" . $nonce;
                $computed_signature = $this->generateHasHmac('sha512', $message, $secret_key);
                $headers = [
                    "content-type" => "application/json",
                    "x-magento-signature" => $computed_signature,
                    'nonce' => $nonce,
                    "partner-id" => $this->getPartnerId()
                ];
            }
            $this->curl->setHeaders($headers);
            if ($requestType == "post") {
                $this->curl->post($url, $params);
            } else {
                if (!empty($params)) {
                    $this->curl->get($url, $params);
                } else {
                    $this->curl->get($url);
                }
            }
            $response = $this->curl->getBody();
            $this->logger->loggedAsInfoData($url, $requestType, $message, $headers, $params, $response);
            return $this->returnResponseData($response);
        } catch (Exception $e) {
            $this->logger->addErrorLog($e->getMessage());
        }
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
        try {
            $memberId = $this->getMemberIdentifierValue();
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
