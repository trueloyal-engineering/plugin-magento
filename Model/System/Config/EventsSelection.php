<?php

namespace TrueLoyal\LoyaltyRewards\Model\System\Config;

use Magento\Framework\Data\OptionSourceInterface;

class EventsSelection implements OptionSourceInterface
{
    /**
     * Too Option Array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'cart_abandonment', 'label' => __('Cart Abandonment')],
            ['value' => 'customer_create', 'label' => __('Customer Create')],
            // ['value' => 'customer_delete', 'label' => __('Customer Delete')],
            ['value' => 'customer_update', 'label' => __('Customer Update')],
            ['value' => 'order_create', 'label' => __('Order Create')],
            // ['value' => 'order_update', 'label' => __('Order Update')],
            ['value' => 'order_complete', 'label' => __('Order Complete')],
            ['value' => 'order_paid', 'label' => __('Order Paid')],
            ['value' => 'order_refund', 'label' => __('Order Refund')],
            ['value' => 'order_cancelled', 'label' => __('Order Cancelled')],
            ['value' => 'order_shipped', 'label' => __('Order Shipped')],
            ['value' => 'review_submitted', 'label' => __('Review Submitted')],
            ['value' => 'review_approved', 'label' => __('Review Approved')]
        ];
    }
}
