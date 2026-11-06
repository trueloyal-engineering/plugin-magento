<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Cron;

use Magento\Framework\Data\Collection;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Cron\RejectRedeemReward;
use TrueLoyal\LoyaltyRewards\Helper\Config;
use TrueLoyal\LoyaltyRewards\Helper\Data;
use TrueLoyal\LoyaltyRewards\Logger\Logger;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalQuote;
use TrueLoyal\LoyaltyRewards\Test\Unit\StoreScopeTrait;

class RejectRedeemRewardTest extends TestCase
{
    use StoreScopeTrait;

    private TestHandler $handler;

    /**
     * The cron runs in the default store 1; stores 2 and 3 have their own events, idle time and webhook URL
     */
    private function cron(array $eventsByStore, Data $helper): RejectRedeemReward&MockObject
    {
        $settings = [];
        foreach ($eventsByStore as $storeId => $events) {
            $settings[$storeId] = [
                Config::XML_PATH_LOYALTY_REWARDS_ACTIVE => '1',
                Config::XML_PATH_AUTO_ENROLLMENT => '1',
                Config::XML_PATH_REWARD_EVENTS => $events,
                Config::XML_PATH_ABANDONED_CART_TIME => $storeId === 2 ? '30' : '1000',
                Config::XML_PATH_WEB_HOOK_URL => 'https://hooks.example.test/store' . $storeId,
                Config::XML_PATH_MEMBER_IDENTIFIER => $storeId === 2 ? 'member_id' : 'member_email',
                Config::XML_PATH_MEMBER_IDENTIFIER_PREFIX => $storeId === 2 ? 'abc_' : '',
            ];
        }
        $this->setStoreScope($helper, $settings, 1);
        $helper->json = new Json();
        $cron = $this->getMockBuilder(RejectRedeemReward::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQuoteCollections'])
            ->getMock();
        $this->handler = new TestHandler();
        foreach ([
            'helper' => $helper,
            'storeManager' => (new \ReflectionProperty(Config::class, 'storeManager'))->getValue($helper),
            'trueloyalLogger' => new Logger('trueloyalLog', [$this->handler]),
        ] as $property => $value) {
            (new \ReflectionProperty(RejectRedeemReward::class, $property))->setValue($cron, $value);
        }
        return $cron;
    }

    private function helper(): Data&MockObject
    {
        return $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTrueLoyalQuoteByQuoteId', 'request'])
            ->getMock();
    }

    public function testGetEnabledStoreIdsReturnsTheStoresWithCartAbandonment(): void
    {
        $cron = $this->cron([
            1 => 'order_create',
            2 => 'order_create,cart_abandonment',
            3 => 'cart_abandonment',
            4 => '',
        ], $this->helper());

        $this->assertSame([2, 3], $cron->getEnabledStoreIds());
    }

    public function testExecuteSkipsTheQuotesWhenNoStoreEnablesCartAbandonment(): void
    {
        $cron = $this->cron([1 => 'order_create', 2 => 'order_create'], $this->helper());
        $cron->expects($this->never())->method('getQuoteCollections');

        $cron->execute();
    }

    public function testExecuteSendsEachQuoteWithItsStoreSettings(): void
    {
        $helper = $this->helper();
        $cron = $this->cron([1 => 'order_create', 2 => 'cart_abandonment'], $helper);
        $trueloyalQuote = $this->getMockBuilder(TrueLoyalQuote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['save'])
            ->getMock();
        $trueloyalQuote->expects($this->once())->method('save');
        $helper->method('getTrueLoyalQuoteByQuoteId')->willReturn($trueloyalQuote);
        $posted = null;
        $helper->expects($this->once())->method('request')
            ->with('https://hooks.example.test/store2')
            ->willReturnCallback(function (string $url, string $params) use (&$posted): array {
                $posted = $params;
                return ['success' => true, 'result' => []];
            });
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getItemsCollection', 'getCustomer', 'debug'])
            ->getMock();
        // Idle for 40 minutes: abandoned after store 2's 30 minutes, not after the default store's 1000
        $quote->setData([
            'store_id' => 2,
            'customer_id' => 37,
            'items_count' => 1,
            'updated_at' => gmdate('Y-m-d H:i:s', time() - 40 * 60),
        ]);
        $quote->method('getId')->willReturn(5);
        $quote->method('getItemsCollection')->willReturn([]);
        $quote->method('getCustomer')->willReturn(null);
        $quote->method('debug')->willReturn(['entity_id' => '5', 'items_qty' => '1.0000']);
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn([$quote]);
        $cron->expects($this->once())->method('getQuoteCollections')->with([2])->willReturn($collection);

        $cron->execute();

        $body = json_decode((string)$posted, true);
        $this->assertSame('abc_37', $body['member_id']);
        $this->assertSame('cart_abandonment', $body['activity_id']);
        $this->assertSame(1, $trueloyalQuote->getData('is_abandoned_cart_sent'));
        $this->assertFalse($this->handler->hasErrorRecords());
        $this->assertSame('https://hooks.example.test/store1', $helper->getWebHookUrl());
    }
}
