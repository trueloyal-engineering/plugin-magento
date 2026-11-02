<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Block\Sales;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use TrueLoyal\LoyaltyRewards\Helper\Data;

/**
 * Adds the redeemed reward's discount row to an order totals block (admin order view, storefront order view, print)
 */
class RewardDiscountTotal extends Template
{
    public function __construct(
        Context $context,
        private readonly Data $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Called by the parent totals block
     */
    public function initTotals(): self
    {
        $order = $this->getParentBlock()->getOrder();
        if (!$order || !$order->getId()) {
            return $this;
        }
        $discount = $this->helper->getRedeemRewardDiscountData($order->getId());
        if ($discount['status']) {
            $this->getParentBlock()->addTotal(
                new DataObject([
                    'code' => 'trueloyal_discount',
                    'strong' => $this->getStrong(),
                    'value' => $discount['value'],
                    'base_value' => $discount['value'],
                    'label' => __($discount['label']),
                ]),
                $this->getAfter()
            );
        }
        return $this;
    }
}
