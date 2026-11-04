<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Helper\Data;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalSalesOrder;
use TrueLoyal\LoyaltyRewards\Observer\CreateOrderAfter;
use TrueLoyal\LoyaltyRewards\Observer\MarkPlacedOrder;

class CreateOrderAfterTest extends TestCase
{
    private const REWARD_DATA = [
        'rule' => 'fixed_amount_discount',
        'reward_value' => 1,
        'id' => 'tx1',
        'coupon_code' => 'REDEEMED_CODE',
    ];

    public static function approveResponses(): array
    {
        $approved = static fn ($data): array => ['success' => true, 'result' => ['data' => $data]];
        return [
            'approved with a coupon code' => [
                $approved(['id' => 'tx1', 'status' => 'approved', 'reward_info' => ['coupon_code' => 'APPROVED_CODE']]),
                'APPROVED_CODE',
            ],
            'approved without a coupon code' => [$approved(['id' => 'tx1', 'reward_info' => []]), 'REDEEMED_CODE'],
            'approved with an empty coupon code' => [$approved(['reward_info' => ['coupon_code' => '']]), 'REDEEMED_CODE'],
            'approved with a non-string coupon code' => [
                $approved(['reward_info' => ['coupon_code' => ['x']]]),
                'REDEEMED_CODE',
            ],
            'failed' => [['success' => false, 'result' => 'Transaction not found'], 'REDEEMED_CODE'],
            'failed with a message object' => [['success' => false, 'result' => new DataObject()], 'REDEEMED_CODE'],
            'no response' => [null, 'REDEEMED_CODE'],
        ];
    }

    #[DataProvider('approveResponses')]
    public function testStoresRedemptionCouponCode(mixed $response, string $expectedCouponCode): void
    {
        $trueloyalOrder = $this->getMockBuilder(TrueLoyalSalesOrder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['save'])
            ->getMock();
        $trueloyalOrder->expects($this->once())->method('save');

        $helper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getTrueLoyalOrderByOrderId',
                'getTrueLoyalQuoteByQuoteId',
                'getRewardRulesData',
                'getLiveWebHookUrl',
                'request',
                'createTrueLoyalOrder',
            ])
            ->getMock();
        $helper->method('getTrueLoyalOrderByOrderId')->willReturn($trueloyalOrder);
        $helper->method('getTrueLoyalQuoteByQuoteId')
            ->willReturn(new DataObject(['redeem_reward_discount' => 'reward1']));
        $helper->method('getRewardRulesData')->willReturn(self::REWARD_DATA);
        $helper->method('getLiveWebHookUrl')->willReturn('https://api.example.test/v2/loyalty/');
        $helper->expects($this->once())->method('request')
            ->with('https://api.example.test/v2/loyalty/transactions/tx1/approve', '', 'post', 'live_api')
            ->willReturn($response);
        $helper->expects($this->once())->method('createTrueLoyalOrder')->with(7, '000000007');

        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIncrementId', 'getQuoteId'])
            ->getMock();
        $order->method('getIncrementId')->willReturn('000000007');
        $order->method('getQuoteId')->willReturn(3);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('get')->with(7)->willReturn($order);
        $quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $quoteRepository->method('get')->with(3)->willReturn($this->createMock(CartInterface::class));

        $observer = new CreateOrderAfter($helper, $orderRepository, $quoteRepository, new Json());
        $placedOrder = new DataObject(['id' => 7, MarkPlacedOrder::FLAG => true]);
        $observer->execute(new Observer(['event' => new Event(['order' => $placedOrder])]));

        $stored = json_decode((string)$trueloyalOrder->getData('trueloyal_reward'), true);
        $this->assertSame(array_replace(self::REWARD_DATA, ['coupon_code' => $expectedCouponCode]), $stored);
        $this->assertSame(7, $trueloyalOrder->getData('order_id'));
    }
}
