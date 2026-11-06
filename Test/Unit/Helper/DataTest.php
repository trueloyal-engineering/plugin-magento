<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Helper;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\DataObject;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Helper\Config;
use TrueLoyal\LoyaltyRewards\Helper\Data;
use TrueLoyal\LoyaltyRewards\Test\Unit\StoreScopeTrait;

class DataTest extends TestCase
{
    use StoreScopeTrait;

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

    public function testMemberIdentifierFollowsTheStoreScope(): void
    {
        $helper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $this->setStoreScope($helper, [
            1 => [Config::XML_PATH_MEMBER_IDENTIFIER => 'member_email'],
            2 => [
                Config::XML_PATH_MEMBER_IDENTIFIER => 'member_id',
                Config::XML_PATH_MEMBER_IDENTIFIER_PREFIX => 'abc_',
            ],
        ], 1);

        $this->assertSame('abc_1400037', $helper->runInStoreScope(
            2,
            fn () => $helper->getMemberIdentifierValueById(1400037, 'member@example.com')
        ));
        $this->assertSame('member@example.com', $helper->getMemberIdentifierValueById(1400037, 'member@example.com'));
    }

    public static function customerScopes(): array
    {
        return [
            'admin, customer in a store of their website' => [0, 2, 4, 4],
            'admin, customer in the default store of their website' => [0, 2, 2, 2],
            'admin, customer without a store' => [0, 2, 0, 2],
            'admin, customer store on another website' => [0, 2, 3, 2],
            'admin, deleted customer store' => [0, 2, 99, null],
            'cron in the default store, customer of another website' => [1, 2, 4, 4],
            'storefront of the customer\'s website' => [4, 2, 2, null],
            'default store, customer of its website' => [1, 1, 3, null],
            'unknown website' => [0, 9, 0, null],
            'no website' => [0, 0, 2, null],
        ];
    }

    #[DataProvider('customerScopes')]
    public function testGetCustomerScopeStoreId(int $currentStoreId, int $websiteId, int $storeId, ?int $expected): void
    {
        $helper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $this->setStoreScope($helper, [], $currentStoreId);
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getWebsiteId')->willReturn((string)$websiteId);
        $customer->method('getStoreId')->willReturn($storeId);

        $this->assertSame($expected, $helper->getCustomerScopeStoreId($customer));
    }

    public function testGetCustomerScopeStoreIdAcceptsTheCustomerModel(): void
    {
        $helper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $this->setStoreScope($helper, [], 0);
        $customer = $this->getMockBuilder(Customer::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $customer->setData(['entity_id' => '37', 'website_id' => '2', 'store_id' => '4']);

        $this->assertSame(4, $helper->getCustomerScopeStoreId($customer));
    }

    public static function reviewScopes(): array
    {
        return [
            'review store' => [['store_id' => '2'], 2],
            'admin store' => [['store_id' => '0'], null],
            'no store' => [[], null],
        ];
    }

    #[DataProvider('reviewScopes')]
    public function testGetReviewScopeStoreId(array $review, ?int $expected): void
    {
        $helper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->assertSame($expected, $helper->getReviewScopeStoreId($review));
    }
}
