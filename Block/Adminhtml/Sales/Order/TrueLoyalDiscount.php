<?php

namespace TrueLoyal\LoyaltyRewards\Block\Adminhtml\Sales\Order;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class TrueLoyalDiscount extends Template
{
    /**
     * @var Order
     */
    protected $order;
    /**
     * @var Source
     */
    protected $source;
    /**
     * @var Data
     */
    private $helper;

    /**
     * TrueLoyal Discount constructor.
     *
     * @param Context $context
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $helper,
        array $data = []
    ) {
        $this->helper = $helper;
        parent::__construct($context, $data);
    }

    /**
     * This function call displayFullSummary
     *
     * @return bool
     */
    public function displayFullSummary()
    {
        return true;
    }

    /**
     * This function call Source
     *
     * @return Source
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * This function call Order
     *
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * This function call LabelProperties
     *
     * @return array
     */
    public function getLabelProperties()
    {
        return $this->getParentBlock()->getLabelProperties();
    }

    /**
     * This function call ValueProperties
     *
     * @return array
     */
    public function getValueProperties()
    {
        return $this->getParentBlock()->getValueProperties();
    }

    /**
     * This function call initTotals
     *
     * @return TrueLoyalDiscount
     */
    public function initTotals()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        $totalAmount = $this->helper->getRedeemRewardDiscountData($orderId);
        if ($totalAmount["status"]) {
            $this->getParentBlock()->addTotal(
                new DataObject(
                    [
                        'code' => 'trueloyal_discount',
                        'strong' => $this->getStrong(),
                        'value' => $totalAmount["value"],
                        'base_value' => $totalAmount["value"],
                        'label' => __($totalAmount["label"]),
                    ]
                ),
                $this->getAfter()
            );
        }
        return $this;
    }

    /**
     * This function call Store
     *
     * @return mixed
     */
    public function getStore()
    {
        return $this->order->getStore();
    }
}
