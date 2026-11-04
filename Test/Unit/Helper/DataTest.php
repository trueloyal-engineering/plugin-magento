<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Helper;

use Magento\Framework\DataObject;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class DataTest extends TestCase
{
    public static function credentials(): array
    {
        return [
            'matching headers' => ['key', 'partner', 'key', 'partner', true],
            'wrong key' => ['key', 'partner', 'wrong', 'partner', false],
            'wrong partner ID' => ['key', 'partner', 'key', 'wrong', false],
            'missing headers' => ['key', 'partner', false, false, false],
            'empty headers' => ['key', 'partner', '', '', false],
            'nothing configured, no headers' => ['', '', false, false, false],
            'nothing configured, empty headers' => ['', '', '', '', false],
        ];
    }

    #[DataProvider('credentials')]
    public function testIsValidateApiAuth(string $key, string $partner, mixed $headerKey, mixed $headerPartner, bool $valid): void
    {
        $helper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getApiKey', 'getPartnerId'])
            ->getMock();
        $helper->method('getApiKey')->willReturn($key);
        $helper->method('getPartnerId')->willReturn($partner);

        $this->assertSame($valid, $helper->isValidateApiAuth($headerKey, $headerPartner));
    }

    public static function couponCodes(): array
    {
        return [
            'no coupon, no reward' => [null, '{}', []],
            'Magento coupon only' => ['SAVE10', '{}', ['SAVE10']],
            'reward coupon only' => [null, '{"id":"tx1","coupon_code":"1$REWARD_38"}', ['1$REWARD_38']],
            'both, Magento coupon first' => ['SAVE10', '{"coupon_code":"1$REWARD_38"}', ['SAVE10', '1$REWARD_38']],
            'same code twice' => ['SAVE10', '{"coupon_code":"SAVE10"}', ['SAVE10']],
            'empty reward coupon' => ['SAVE10', '{"coupon_code":""}', ['SAVE10']],
            'non-string reward coupon' => ['SAVE10', '{"coupon_code":123}', ['SAVE10']],
            'no TrueLoyal order row' => ['SAVE10', null, ['SAVE10']],
            'invalid JSON' => ['SAVE10', 'not json', ['SAVE10']],
            'JSON that is not an object' => ['SAVE10', '"1$REWARD_38"', ['SAVE10']],
            'already a list (second call on the same order)' => [
                ['SAVE10', '1$REWARD_38'],
                '{"coupon_code":"1$REWARD_38"}',
                ['SAVE10', '1$REWARD_38'],
            ],
            'empty list' => [[], '{"coupon_code":"GIFT_7"}', ['GIFT_7']],
        ];
    }

    #[DataProvider('couponCodes')]
    public function testGetCouponCodes(mixed $magentoCoupon, ?string $trueloyalReward, array $expected): void
    {
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCouponCode', 'getId'])
            ->getMock();
        $order->method('getCouponCode')->willReturn($magentoCoupon);
        $order->method('getId')->willReturn('42');
        $helper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTrueLoyalOrderByOrderId'])
            ->getMock();
        $helper->expects($this->once())->method('getTrueLoyalOrderByOrderId')->with('42')
            ->willReturn(new DataObject(['trueloyal_reward' => $trueloyalReward]));

        $this->assertSame($expected, $helper->getCouponCodes($order));
    }

    public function testGetCouponCodesWithoutOrderIdSkipsLookup(): void
    {
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCouponCode', 'getId'])
            ->getMock();
        $order->method('getCouponCode')->willReturn('SAVE10');
        $order->method('getId')->willReturn(null);
        $helper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTrueLoyalOrderByOrderId'])
            ->getMock();
        $helper->expects($this->never())->method('getTrueLoyalOrderByOrderId');

        $this->assertSame(['SAVE10'], $helper->getCouponCodes($order));
    }
}
