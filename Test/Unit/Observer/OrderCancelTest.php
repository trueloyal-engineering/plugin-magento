<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Helper\Config;
use TrueLoyal\LoyaltyRewards\Helper\Data;
use TrueLoyal\LoyaltyRewards\Logger\Logger;
use TrueLoyal\LoyaltyRewards\Observer\OrderCancel;
use TrueLoyal\LoyaltyRewards\Test\Unit\StoreScopeTrait;

class OrderCancelTest extends TestCase
{
    use StoreScopeTrait;

    public static function orderStoreEvents(): array
    {
        return [
            'disabled on the order\'s website' => ['order_create,order_paid', false],
            'enabled on the order\'s website' => ['order_create,order_cancelled', true],
        ];
    }

    /**
     * The admin (store 0) enables every event with the email as member ID; the order's store 2 has its own events,
     * member ID rule, webhook URL and keys
     */
    #[DataProvider('orderStoreEvents')]
    public function testCancelInTheAdminUsesTheOrderStoreSettings(string $store2Events, bool $sent): void
    {
        $common = [Config::XML_PATH_LOYALTY_REWARDS_ACTIVE => '1', Config::XML_PATH_AUTO_ENROLLMENT => '1'];
        $helper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCouponCodes', 'getReplacedOrderID'])
            ->getMock();
        $helper->method('getCouponCodes')->willReturn([]);
        $helper->method('getReplacedOrderID')->willReturn('000000009');
        $helper->json = new Json();
        $helper->logger = new Logger('trueloyalLog', [new TestHandler()]);
        $this->setStoreScope($helper, [
            0 => $common + [
                Config::XML_PATH_REWARD_EVENTS => 'order_create,order_cancelled',
                Config::XML_PATH_MEMBER_IDENTIFIER => 'member_email',
                Config::XML_PATH_WEB_HOOK_URL => 'https://hooks.example.test/default',
                Config::XML_PATH_API_KEY => 'default-key',
                Config::XML_PATH_PARTNER_ID => 'default-partner',
            ],
            2 => $common + [
                Config::XML_PATH_REWARD_EVENTS => $store2Events,
                Config::XML_PATH_MEMBER_IDENTIFIER => 'member_id',
                Config::XML_PATH_MEMBER_IDENTIFIER_PREFIX => 'abc_',
                Config::XML_PATH_WEB_HOOK_URL => 'https://hooks.example.test/website2',
                Config::XML_PATH_API_KEY => 'website2-key',
                Config::XML_PATH_PARTNER_ID => 'website2-partner',
            ],
        ], 0);
        $headers = [];
        $posted = null;
        $curl = $this->createMock(Curl::class);
        $curl->method('setHeaders')->willReturnCallback(function (array $sentHeaders) use (&$headers): void {
            $headers = $sentHeaders;
        });
        $curl->expects($sent ? $this->once() : $this->never())->method('post')
            ->with('https://hooks.example.test/website2')
            ->willReturnCallback(function (string $url, string $body) use (&$posted): void {
                $posted = $body;
            });
        $curl->method('getBody')->willReturn('{"message":"Workflow was started"}');
        (new \ReflectionProperty(Config::class, 'curl'))->setValue($helper, $curl);

        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getStoreId', 'getPayment', 'getEntityId', 'debug', 'getItems', 'getCustomerId', 'getCustomerEmail',
            ])
            ->getMock();
        $order->method('getStoreId')->willReturn('2');
        $order->method('getPayment')->willReturn(new DataObject(['method' => 'checkmo']));
        $order->method('getEntityId')->willReturn('9');
        $order->method('debug')->willReturn(['grand_total' => '10.0000', 'total_qty_ordered' => '1.0000']);
        $order->method('getItems')->willReturn([]);
        $order->method('getCustomerId')->willReturn('37');
        $order->method('getCustomerEmail')->willReturn('member@example.com');

        $observer = new OrderCancel($helper, $this->createMock(OrderRepositoryInterface::class));
        $observer->execute(new Observer(['event' => new Event(['order' => $order])]));

        if ($sent) {
            $body = json_decode((string)$posted, true);
            $this->assertSame('abc_37', $body['member_id']);
            $this->assertSame('order_cancel', $body['activity_id']);
            $this->assertSame('000000009', $body['data']['order_id']);
            $this->assertSame('website2-partner', $headers['partner-id']);
            $this->assertSame(
                hash_hmac('sha512', $posted . ':' . $headers['nonce'], 'website2-key'),
                $headers['x-magento-signature']
            );
        }
        // Later work in the same request reads the current store again
        $this->assertSame('https://hooks.example.test/default', $helper->getWebHookUrl());
    }
}
