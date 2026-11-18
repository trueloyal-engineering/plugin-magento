<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Controller\LoyaltyRewards;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Controller\LoyaltyRewards\ApplyPoint;
use TrueLoyal\LoyaltyRewards\Helper\Data;
use TrueLoyal\LoyaltyRewards\Logger\Logger;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalQuote;

/**
 * The coupon code of a pending redemption is stored on the quote's reward rule, so it reaches the order
 * (CreateOrderAfter) and goes out with the order events that let TrueLoyal give the points back.
 */
class ApplyPointTest extends TestCase
{
    private const RULES = [
        'rwd_fixed' => ['reward_id' => 'rwd_fixed', 'rule' => 'fixed_amount_discount', 'reward_name' => '$5 off'],
        'rwd_other' => ['reward_id' => 'rwd_other', 'rule' => 'percentage_discount', 'reward_name' => '10% off'],
    ];

    private TestHandler $handler;

    private Data&MockObject $helper;

    private TrueLoyalQuote&MockObject $trueloyalQuote;

    protected function setUp(): void
    {
        $this->trueloyalQuote = $this->getMockBuilder(TrueLoyalQuote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['save'])
            ->getMock();
        $this->trueloyalQuote->setData('reward_rules_data', json_encode(self::RULES));

        $this->helper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTrueLoyalQuoteByQuoteId'])
            ->getMock();
        $this->helper->json = new Json();
        $this->helper->method('getTrueLoyalQuoteByQuoteId')->willReturn($this->trueloyalQuote);
    }

    private function controller(): ApplyPoint
    {
        $controller = (new \ReflectionClass(ApplyPoint::class))->newInstanceWithoutConstructor();
        $this->handler = new TestHandler();
        foreach (['helper' => $this->helper, 'logger' => new Logger('trueloyalLog', [$this->handler])] as $name => $value) {
            (new \ReflectionProperty(ApplyPoint::class, $name))->setValue($controller, $value);
        }
        return $controller;
    }

    /**
     * The redeem response (transactions/redeem, status pending) for reward rwd_fixed
     */
    private function response(array $rewardInfo): array
    {
        return ['id' => 'tx_123', 'status' => 'pending', 'reward_info' => ['reward_id' => 'rwd_fixed'] + $rewardInfo];
    }

    private function storedRules(): array
    {
        return json_decode((string)$this->trueloyalQuote->getData('reward_rules_data'), true);
    }

    public function testSaveQuoteDataStoresTheRedemptionCouponCodeOnTheRedeemedRule(): void
    {
        $this->trueloyalQuote->expects($this->once())->method('save');

        $this->controller()->saveQuoteData(
            $this->trueloyalQuote,
            $this->response(['coupon_code' => '1$REWARD_38']),
            'rwd_fixed',
            self::RULES['rwd_fixed']
        );

        $rules = $this->storedRules();
        $this->assertSame('1$REWARD_38', $rules['rwd_fixed']['coupon_code']);
        $this->assertSame('tx_123', $rules['rwd_fixed']['id']);
        $this->assertSame(self::RULES['rwd_other'], $rules['rwd_other']);
        $this->assertSame('rwd_fixed', $this->trueloyalQuote->getData('redeem_reward_discount'));
        $this->assertFalse($this->handler->hasCriticalRecords());
    }

    public function testTheStoredCouponCodeIsWhatTheOrderReadsFromTheQuote(): void
    {
        $this->controller()->saveQuoteData(
            $this->trueloyalQuote,
            $this->response(['coupon_code' => '1$REWARD_38']),
            'rwd_fixed',
            self::RULES['rwd_fixed']
        );

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $quote->method('getId')->willReturn(5);

        // CreateOrderAfter copies this rule to the order (trueloyal_reward); Data::getCouponCodes() sends it
        $rule = $this->helper->getRewardRulesData($quote);
        $this->assertSame('1$REWARD_38', $rule['coupon_code']);
        $this->assertSame('tx_123', $rule['id']);
    }

    /**
     * @dataProvider noCouponCodeProvider
     */
    public function testSaveQuoteDataStoresAnEmptyCouponCodeWhenTheResponseHasNone(array $rewardInfo): void
    {
        $this->trueloyalQuote->expects($this->once())->method('save');

        $this->controller()->saveQuoteData(
            $this->trueloyalQuote,
            $this->response($rewardInfo),
            'rwd_fixed',
            self::RULES['rwd_fixed']
        );

        // An empty string, which Data::getCouponCodes() leaves out of the order's coupon list
        $this->assertSame('', $this->storedRules()['rwd_fixed']['coupon_code']);
        $this->assertFalse($this->handler->hasCriticalRecords());
    }

    public static function noCouponCodeProvider(): array
    {
        return [
            'missing' => [[]],
            'null' => [['coupon_code' => null]],
            'empty' => [['coupon_code' => '']],
        ];
    }

    public function testSaveQuoteDataReplacesTheCouponCodeOfAnEarlierRedemption(): void
    {
        $rules = self::RULES;
        $rules['rwd_fixed'] += ['id' => 'tx_old', 'coupon_code' => 'OLD_CODE'];
        $this->trueloyalQuote->setData('reward_rules_data', json_encode($rules));

        $this->controller()->saveQuoteData(
            $this->trueloyalQuote,
            $this->response(['coupon_code' => 'NEW_CODE']),
            'rwd_fixed',
            self::RULES['rwd_fixed']
        );

        $stored = $this->storedRules()['rwd_fixed'];
        $this->assertSame('NEW_CODE', $stored['coupon_code']);
        $this->assertSame('tx_123', $stored['id']);
    }
}
