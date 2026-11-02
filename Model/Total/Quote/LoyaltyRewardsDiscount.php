<?php

namespace TrueLoyal\LoyaltyRewards\Model\Total\Quote;

use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use TrueLoyal\LoyaltyRewards\Helper\Data;
use TrueLoyal\LoyaltyRewards\Model\Reward\DiscountCalculator;

class LoyaltyRewardsDiscount extends AbstractTotal
{
    /**
     * LoyaltyRewardsDiscount constructor.
     *
     * @param Data $helper
     */
    public function __construct(
        private readonly Data $helper,
        private readonly DiscountCalculator $discountCalculator
    ) {
    }

    /**
     * Quote show discount show in order view page
     *
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return $this|LoyaltyRewardsDiscount
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        parent::collect($quote, $shippingAssignment, $total);
        $rewardData = $this->helper->getRewardRulesData($quote);
        if ($this->discountCalculator->isDiscount($rewardData)) {
            $totalAmount = $this->discountCalculator->amount($rewardData, $total->getSubtotal());
            $total->addTotalAmount('trueloyal_discount', $totalAmount);
            $total->addBaseTotalAmount('trueloyal_discount', $totalAmount);
            $quote->setCustomDiscount($totalAmount);
        }
        return $this;
    }

    /**
     * Assign subtotal amount and label to address object
     *
     * @param Quote $quote
     * @param Total $total
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function fetch(Quote $quote, Total $total)
    {
        $rewardData = $this->helper->getRewardRulesData($quote);
        if ($this->discountCalculator->isDiscount($rewardData) || ($rewardData['rule'] ?? null) === 'product_redemption') {
            return [
                'code' => 'trueloyal_discount',
                'title' => __($this->helper->getRewardAppliedRuleLabel($quote)),
                /* A gift has no amount: the row only names it */
                'value' => $this->discountCalculator->amount($rewardData, $total->getSubtotal()) ?? ''
            ];
        }
        return [];
    }
}
