<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Model\Reward;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Model\Reward\DiscountCalculator;

class DiscountCalculatorTest extends TestCase
{
    public static function rewards(): array
    {
        return [
            'fixed' => [['rule' => 'fixed_amount_discount', 'reward_value' => '1'], 50, -1, '1'],
            'fixed, decimals' => [['rule' => 'fixed_amount_discount', 'reward_value' => '7.5'], 50, -7.5, '7.5'],
            'percentage' => [['rule' => 'percentage_discount', 'reward_value' => '10'], '75.0000', -7.5, '10'],
            'flexible points' => [['rule' => 'flexible_points_reward', 'points_to_be_redeemed' => '50',
                'conversion_rate' => '100'], 50, -0.5, 0.5],
            'gift' => [['rule' => 'product_redemption', 'reward_value' => ''], 50, null, ''],
            'free shipping' => [['rule' => 'free_shipping', 'reward_value' => ''], 50, null, ''],
        ];
    }

    #[DataProvider('rewards')]
    public function testAmountAndValue(array $reward, mixed $subtotal, int|float|null $amount, mixed $value): void
    {
        $calculator = new DiscountCalculator();
        $this->assertSame($amount, $calculator->amount($reward, $subtotal));
        $this->assertSame($amount !== null, $calculator->isDiscount($reward));
        $this->assertSame($value, $calculator->value($reward));
    }

    public function testNoReward(): void
    {
        $this->assertFalse((new DiscountCalculator())->isDiscount([]));
        $this->assertNull((new DiscountCalculator())->amount([], 50));
    }
}
