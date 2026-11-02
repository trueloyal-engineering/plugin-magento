<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\Reward;

/**
 * The discount a redeemed reward gives (the one formula for the cart total, the order and the sales documents)
 *
 * fixed_amount_discount: reward_value; percentage_discount: subtotal x reward_value %; flexible_points_reward:
 * points_to_be_redeemed / conversion_rate. Gifts (product_redemption) and free shipping aren't discounts.
 */
class DiscountCalculator
{
    public const DISCOUNT_RULES = ['fixed_amount_discount', 'percentage_discount', 'flexible_points_reward'];

    public function isDiscount(array $reward): bool
    {
        return in_array($reward['rule'] ?? null, self::DISCOUNT_RULES, true);
    }

    /**
     * The discount as a negative amount, null when the reward isn't a discount
     *
     * @param array $reward the applied reward rule (trueloyal_quote.reward_rules_data entry)
     * @param mixed $subtotal the subtotal the percentage applies to
     */
    public function amount(array $reward, mixed $subtotal): int|float|null
    {
        return match ($reward['rule'] ?? null) {
            'fixed_amount_discount' => -$reward['reward_value'],
            'percentage_discount' => -$subtotal * $reward['reward_value'] / 100,
            'flexible_points_reward' => -($reward['points_to_be_redeemed'] / $reward['conversion_rate']),
            default => null,
        };
    }

    /**
     * The reward's value to spread over the items: percent for percentage rewards, an amount for the others
     */
    public function value(array $reward): mixed
    {
        return $reward['rule'] === 'flexible_points_reward'
            ? $reward['points_to_be_redeemed'] / $reward['conversion_rate']
            : $reward['reward_value'];
    }
}
