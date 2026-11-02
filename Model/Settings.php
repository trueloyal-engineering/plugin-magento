<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model;

use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Typed access to the module's configuration (Stores > Configuration > TrueLoyal)
 *
 * Values are read at store scope: the given store, or the current one (the default store in cron and admin).
 */
class Settings
{
    private const PREFIX = 'trueloyal_loyaltyRewards/settings/';
    public const XML_PATH_LOYALTY_REWARDS_ACTIVE = self::PREFIX . 'loyalty_rewards_active';
    public const XML_PATH_DASHBOARD_HIDDEN_FOR_GUESTS = self::PREFIX . 'hide_for_guests';
    public const XML_PATH_WEB_HOOK_URL = self::PREFIX . 'web_hook_url';
    public const XML_PATH_WEBHOOK_INTEGRATION_ID = self::PREFIX . 'webhook_integration_id';
    public const XML_PATH_WEBHOOK_INTEGRATION_URL = self::PREFIX . 'webhook_integration_url';
    public const XML_PATH_LIVE_WEB_HOOK_URL = self::PREFIX . 'live_web_hook_url';
    public const XML_PATH_ABANDONED_CART_TIME = self::PREFIX . 'abandoned_cart_time';
    public const XML_PATH_PARTNER_ID = self::PREFIX . 'partner_id';
    public const XML_PATH_API_KEY = self::PREFIX . 'api_key';
    public const XML_PATH_API_KEY_IDENTIFIER = self::PREFIX . 'api_key_identifier';
    public const XML_PATH_REWARD_EVENTS = self::PREFIX . 'reward_events';
    public const XML_PATH_REWARDS_DROPDOWN_ACTIVE = self::PREFIX . 'rewards_event_drop_down_active';
    public const XML_PATH_REWARDS_POINTS_AT_PDP = self::PREFIX . 'product_page_rewards_point_enable';
    public const XML_PATH_FREE_SHIPPING_LABEL = self::PREFIX . 'free_shipping_label';
    public const XML_PATH_PRODUCT_PAGE_REWARD_LABEL = self::PREFIX . 'product_page_reward_label';
    public const XML_PATH_PDP_CART_PAGE = self::PREFIX . 'cart_page_rewards_point_enable';
    public const XML_PATH_PDP_CART_PAGE_REWARD_LABEL = self::PREFIX . 'cart_page_reward_label';
    public const XML_PATH_CART_PAGE_REWARD_DROPDOWN_LABEL = self::PREFIX . 'cart_page_reward_dropdown_label';
    public const XML_PATH_LANGUAGES = self::PREFIX . 'languages_mapping';
    public const XML_PATH_AUTO_ENROLLMENT = self::PREFIX . 'auto_enrollment';
    public const XML_PATH_OPT_IN_FIELD_NAME = self::PREFIX . 'opt_in_field_name';
    public const XML_PATH_MEMBER_IDENTIFIER_PREFIX = self::PREFIX . 'member_identifier_prefix';
    public const XML_PATH_MEMBER_IDENTIFIER = self::PREFIX . 'member_identifier';
    public const XML_PATH_CUSTOM_MEMBER_STORE_CODE = self::PREFIX . 'custom_member_attributes/store_code';
    public const XML_PATH_CUSTOM_MEMBER_STORE_ID = self::PREFIX . 'custom_member_attributes/store_id';
    public const XML_PATH_CUSTOM_MEMBER_STORE_CURRENCY = self::PREFIX . 'custom_member_attributes/store_currency';
    public const XML_PATH_PREFERRED_LANGUAGE = self::PREFIX . 'preferred_language';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly WriterInterface $configWriter,
        private readonly ReinitableConfigInterface $reinitableConfig
    ) {
    }

    public function getValue(string $path, int|string|null $storeId = null): mixed
    {
        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId ?? $this->storeManager->getStore()->getId()
        );
    }

    /**
     * Save a value at default scope and make it visible to the rest of this request
     *
     * The admin config save rebuilds the config cache before its observers run: without the reinit, the store would
     * keep using the old value (e.g. post to the old webhook URL) until the next cache clean.
     */
    public function save(string $path, ?string $value): void
    {
        $this->configWriter->save($path, $value);
        $this->reinitableConfig->reinit();
    }

    public function isActive(int|string|null $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_LOYALTY_REWARDS_ACTIVE, $storeId);
    }

    public function getApiKey(int|string|null $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_API_KEY, $storeId);
    }

    public function getApiKeyIdentifier(int|string|null $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_API_KEY_IDENTIFIER, $storeId);
    }

    public function getPartnerId(int|string|null $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_PARTNER_ID, $storeId);
    }

    /** n8n workflow receiving the activity events */
    public function getWebhookUrl(int|string|null $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_WEB_HOOK_URL, $storeId);
    }

    /** Base URL of the live API, e.g. https://api.trueloyal.com/v2/loyalty/ */
    public function getLiveApiUrl(int|string|null $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_LIVE_WEB_HOOK_URL, $storeId);
    }

    public function getIntegrationId(int|string|null $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_WEBHOOK_INTEGRATION_ID, $storeId);
    }

    /**
     * Activity events selected in the admin, as saved (order_refund not yet expanded)
     *
     * @return string[]
     */
    public function getRewardEvents(int|string|null $storeId = null): array
    {
        $events = (string)$this->getValue(self::XML_PATH_REWARD_EVENTS, $storeId);
        return $events === '' ? [] : explode(',', $events);
    }

    /** 'member_email' (default) or 'member_id' */
    public function getMemberIdentifierType(int|string|null $storeId = null): string
    {
        return (string)($this->getValue(self::XML_PATH_MEMBER_IDENTIFIER, $storeId) ?? 'member_email');
    }

    public function getMemberIdentifierPrefix(int|string|null $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_MEMBER_IDENTIFIER_PREFIX, $storeId);
    }

    public function isAutoEnrollmentEnabled(int|string|null $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_AUTO_ENROLLMENT, $storeId);
    }

    public function getOptInAttributeCode(int|string|null $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_OPT_IN_FIELD_NAME, $storeId);
    }
}
