<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\Reward;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use TrueLoyal\LoyaltyRewards\Logger\Logger;
use TrueLoyal\LoyaltyRewards\Model\Client\LiveApi;

/**
 * A member's balance and rewards, and the redemption calls (redeem, approve, reject)
 *
 * Balance and rewards are cached in the storefront customer session for a minute, so moving around the cart doesn't
 * call TrueLoyal on every page; redeeming, approving and rejecting clear the cache.
 */
class RewardService
{
    private const CACHE_KEY = 'trueloyal_live_cache';
    private const CACHE_TTL = 60;

    /** @var array<string, array{success: bool, result: mixed}> responses of this request */
    private array $responses = [];

    public function __construct(
        private readonly LiveApi $liveApi,
        private readonly CustomerSession $customerSession,
        private readonly State $appState,
        private readonly Logger $logger
    ) {
    }

    /**
     * GET members/{memberId}
     *
     * @return array{success: bool, result: mixed}|null null when TrueLoyal can't be reached
     */
    public function getMember(string $memberId): ?array
    {
        return $this->cachedGet($this->liveApi->memberUrl($memberId));
    }

    /**
     * GET members/{memberId}/rewards
     *
     * @return array{success: bool, result: mixed}|null null when TrueLoyal can't be reached
     */
    public function getRewards(string $memberId): ?array
    {
        return $this->cachedGet($this->liveApi->memberUrl($memberId, '/rewards'));
    }

    /**
     * POST transactions/redeem: put the points on hold (status pending)
     *
     * @return array{success: bool, result: mixed}|null
     */
    public function redeem(string $body): ?array
    {
        return $this->post($this->liveApi->transactionUrl('redeem'), $body);
    }

    public function approve(string $transactionId, int|string|null $storeId = null): ?array
    {
        return $this->post($this->liveApi->transactionUrl($transactionId . '/approve', $storeId), '', $storeId);
    }

    public function reject(string $transactionId): ?array
    {
        return $this->post($this->liveApi->transactionUrl($transactionId . '/reject'), '');
    }

    /**
     * Forget cached balances and rewards (after anything that changes them)
     */
    public function clearCache(): void
    {
        $this->responses = [];
        $this->session()?->unsetData(self::CACHE_KEY);
    }

    /**
     * The customer session, storefront only (cron, admin and API calls have none)
     */
    private function session(): ?CustomerSession
    {
        try {
            return $this->appState->getAreaCode() === Area::AREA_FRONTEND ? $this->customerSession : null;
        } catch (\Magento\Framework\Exception\LocalizedException) {
            return null;
        }
    }

    private function cachedGet(string $url): ?array
    {
        if (array_key_exists($url, $this->responses)) {
            return $this->responses[$url];
        }
        $session = $this->session();
        $cache = $session?->getData(self::CACHE_KEY) ?: [];
        if (isset($cache[$url]) && $cache[$url]['until'] > time()) {
            return $this->responses[$url] = $cache[$url]['response'];
        }
        $response = $this->call('get', $url);
        if ($session && $response !== null && $response['success']) {
            $cache[$url] = ['until' => time() + self::CACHE_TTL, 'response' => $response];
            $session->setData(self::CACHE_KEY, $cache);
        }
        return $this->responses[$url] = $response;
    }

    private function post(string $url, string $body, int|string|null $storeId = null): ?array
    {
        $this->clearCache();
        $response = $this->call('post', $url, $body, $storeId);
        $this->clearCache();
        return $response;
    }

    private function call(string $method, string $url, string $body = '', int|string|null $storeId = null): ?array
    {
        try {
            return $this->liveApi->request($method, $url, $body, $storeId);
        } catch (\Exception $e) {
            $this->logger->addErrorLog($e->getMessage());
            return null;
        }
    }
}
