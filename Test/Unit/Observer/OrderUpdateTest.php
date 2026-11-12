<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Observer;

use Magento\Framework\App\Request\Http;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Status\History;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Helper\Config;
use TrueLoyal\LoyaltyRewards\Helper\Data;
use TrueLoyal\LoyaltyRewards\Logger\Logger;
use TrueLoyal\LoyaltyRewards\Observer\OrderUpdate;
use TrueLoyal\LoyaltyRewards\Test\Unit\StoreScopeTrait;

class OrderUpdateTest extends TestCase
{
    use StoreScopeTrait;

    private const DEFAULT_URL = 'https://hooks.example.test/default';
    private const WEBSITE2_URL = 'https://hooks.example.test/website2';

    private Data&MockObject $helper;
    private Curl&MockObject $curl;
    private ?string $posted = null;

    public static function commentRequests(): array
    {
        return [
            // a status history saved on its own (e.g. through OrderStatusHistoryRepositoryInterface::save()) has no order
            'History saved on its own, no order, enabled on the order\'s website' => [null, '9', false, 1, true],
            'History saved on its own, no order, disabled on the order\'s website' => [null, '9', false, 1, false],
            // Admin comment form: the history belongs to the order
            'admin, history of the order, enabled on the order\'s website' => ['9', null, true, 0, true],
            'admin, history of the order, disabled on the order\'s website' => ['9', null, true, 0, false],
        ];
    }

    /**
     * The current store enables order_update with the email as member ID; the order's store 2 has its own events,
     * member ID rule and webhook URL
     */
    #[DataProvider('commentRequests')]
    public function testCommentUsesTheSettingsOfTheOrderLoadedById(
        ?string $orderIdParam,
        ?string $restIdParam,
        bool $historyHasOrder,
        int $currentStoreId,
        bool $enabledOnWebsite2
    ): void {
        $this->createHelper($currentStoreId, $enabledOnWebsite2 ? 'order_create,order_update' : 'order_create');
        $order = $this->createOrder();
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        // Loaded once, before the events check, and reused for the payload
        $orderRepository->expects($this->once())->method('get')->with('9')->willReturn($order);
        $this->curl->expects($enabledOnWebsite2 ? $this->once() : $this->never())->method('post')
            ->with(self::WEBSITE2_URL);

        $result = $this->createObserver($orderRepository, $orderIdParam, $restIdParam)
            ->execute($this->createEvent($historyHasOrder ? $order : null));

        if ($enabledOnWebsite2) {
            $this->assertTrue($result);
            $body = json_decode((string)$this->posted, true);
            $this->assertSame('abc_37', $body['member_id']);
            $this->assertSame('order_update', $body['activity_id']);
            $this->assertSame('000000009', $body['data']['order_id']);
            $this->assertSame('Shipped by courier', $body['data']['last_comment']);
        } else {
            $this->assertNull($result);
        }
        // Later work in the same request reads the current store again
        $this->assertSame(self::DEFAULT_URL, $this->helper->getWebHookUrl());
    }

    /**
     * An unknown order keeps the current store; the comment save only fails where it failed before (event enabled)
     */
    #[DataProvider('unknownOrders')]
    public function testUnknownOrderUsesTheCurrentStore(?string $restIdParam, bool $enabledOnCurrentStore): void
    {
        $this->createHelper(1, 'order_create,order_update', $enabledOnCurrentStore ? null : 'order_create');
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('get')->willThrowException(new NoSuchEntityException());
        $this->curl->expects($this->never())->method('post');
        if ($enabledOnCurrentStore) {
            $this->expectException(NoSuchEntityException::class);
        }

        $result = $this->createObserver($orderRepository, null, $restIdParam)->execute($this->createEvent(null));

        $this->assertNull($result);
        $this->assertSame([1], array_unique($this->configStoreIds));
    }

    public static function unknownOrders(): array
    {
        return [
            'unknown ID, disabled on the current store' => ['404', false],
            'no ID, disabled on the current store' => [null, false],
            'unknown ID, enabled on the current store' => ['404', true],
        ];
    }

    /**
     * Helper with the settings of stores 0 and 1 (current stores) and 2 (the order's store)
     */
    private function createHelper(int $currentStoreId, string $store2Events, ?string $currentEvents = null): void
    {
        $common = [Config::XML_PATH_LOYALTY_REWARDS_ACTIVE => '1', Config::XML_PATH_AUTO_ENROLLMENT => '1'];
        $current = $common + [
            Config::XML_PATH_REWARD_EVENTS => $currentEvents ?? 'order_create,order_update',
            Config::XML_PATH_MEMBER_IDENTIFIER => 'member_email',
            Config::XML_PATH_WEB_HOOK_URL => self::DEFAULT_URL,
        ];
        $this->helper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCouponCodes', 'getReplacedOrderID'])
            ->getMock();
        $this->helper->method('getCouponCodes')->willReturn([]);
        $this->helper->method('getReplacedOrderID')->willReturn('000000009');
        $this->helper->json = new Json();
        $this->helper->logger = new Logger('trueloyalLog', [new TestHandler()]);
        $this->setStoreScope($this->helper, [
            0 => $current,
            1 => $current,
            2 => $common + [
                Config::XML_PATH_REWARD_EVENTS => $store2Events,
                Config::XML_PATH_MEMBER_IDENTIFIER => 'member_id',
                Config::XML_PATH_MEMBER_IDENTIFIER_PREFIX => 'abc_',
                Config::XML_PATH_WEB_HOOK_URL => self::WEBSITE2_URL,
            ],
        ], $currentStoreId);
        $this->curl = $this->createMock(Curl::class);
        $this->curl->method('post')->willReturnCallback(function (string $url, string $body): void {
            $this->posted = $body;
        });
        $this->curl->method('getBody')->willReturn('{"message":"Workflow was started"}');
        (new \ReflectionProperty(Config::class, 'curl'))->setValue($this->helper, $this->curl);
    }

    private function createOrder(): Order
    {
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getStoreId', 'getPayment', 'getEntityId', 'debug', 'getItems', 'getStatusHistories',
                'getCustomerId', 'getCustomerEmail',
            ])
            ->getMock();
        $order->method('getStoreId')->willReturn('2');
        $order->method('getPayment')->willReturn(new DataObject(['method' => 'checkmo']));
        $order->method('getEntityId')->willReturn('9');
        $order->method('debug')->willReturn(['grand_total' => '10.0000', 'total_qty_ordered' => '1.0000']);
        $order->method('getItems')->willReturn([]);
        $order->method('getStatusHistories')->willReturn([]);
        $order->method('getCustomerId')->willReturn('37');
        $order->method('getCustomerEmail')->willReturn('member@example.com');
        return $order;
    }

    private function createObserver(
        OrderRepositoryInterface $orderRepository,
        ?string $orderIdParam,
        ?string $restIdParam
    ): OrderUpdate {
        $request = $this->createMock(Http::class);
        $request->method('getParam')->willReturnMap([
            ['history', null, ['comment' => 'Shipped by courier']],
            ['order_id', null, $orderIdParam],
        ]);
        $restRequest = $this->createMock(Request::class);
        $restRequest->method('getParam')->willReturnMap([['id', null, $restIdParam]]);
        return new OrderUpdate($this->helper, $request, $restRequest, $orderRepository);
    }

    private function createEvent(?Order $historyOrder): Observer
    {
        $history = $this->getMockBuilder(History::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrder'])
            ->getMock();
        $history->method('getOrder')->willReturn($historyOrder);
        return new Observer(['event' => new Event(['status_history' => $history])]);
    }
}
