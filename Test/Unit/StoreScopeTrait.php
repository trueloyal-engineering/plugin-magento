<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use TrueLoyal\LoyaltyRewards\Helper\Config;

/**
 * Settings per store view for helper tests. Stores: 0 = admin (website 0), 1 and 3 on website 1, 2 and 4 on
 * website 2; the first store of each website is its default store.
 */
trait StoreScopeTrait
{
    /**
     * Store IDs getConfig() read settings from, in order
     *
     * @var array
     */
    private array $configStoreIds = [];

    /**
     * @param Config $helper
     * @param array $values settings by store ID and config path
     * @param int $currentStoreId the store the request runs in (0 = admin)
     */
    private function setStoreScope(Config $helper, array $values, int $currentStoreId): void
    {
        $storeWebsites = [0 => 0, 1 => 1, 2 => 2, 3 => 1, 4 => 2];
        $websiteDefaultStores = [1 => 1, 2 => 2];

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            function (string $path, string $scope = 'default', $storeId = null) use ($values) {
                $this->configStoreIds[] = $storeId;
                return $values[(int)$storeId][$path] ?? null;
            }
        );
        $store = static fn (int $id): DataObject =>
            new DataObject(['id' => $id, 'store_id' => $id, 'website_id' => $storeWebsites[$id]]);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturnCallback(
            static function ($storeId = null) use ($store, $storeWebsites, $currentStoreId): DataObject {
                $storeId = $storeId === null ? $currentStoreId : (int)$storeId;
                if (!isset($storeWebsites[$storeId])) {
                    throw new NoSuchEntityException();
                }
                return $store($storeId);
            }
        );
        $storeManager->method('getStores')->willReturnCallback(
            static fn (): array => array_map($store, array_values(array_filter(array_keys($storeWebsites))))
        );
        $storeManager->method('getWebsite')->willReturnCallback(
            static function ($websiteId) use ($websiteDefaultStores): DataObject {
                if (!isset($websiteDefaultStores[(int)$websiteId])) {
                    throw new NoSuchEntityException();
                }
                return new DataObject(['id' => (int)$websiteId, 'default_group_id' => (int)$websiteId]);
            }
        );
        $storeManager->method('getGroup')->willReturnCallback(
            static fn ($groupId): DataObject => new DataObject([
                'id' => (int)$groupId,
                'default_store_id' => $websiteDefaultStores[(int)$groupId],
            ])
        );
        (new \ReflectionProperty(Config::class, 'scopeConfig'))->setValue($helper, $scopeConfig);
        (new \ReflectionProperty(Config::class, 'storeManager'))->setValue($helper, $storeManager);
    }
}
