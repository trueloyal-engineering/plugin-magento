<?php

namespace TrueLoyal\LoyaltyRewards\Block\Adminhtml\Sales\Order;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class TrueLoyalInvoiceCreateDiscount extends Template
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
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * TrueLoyal Invoice Create Discount constructor.
     *
     * @param Context $context
     * @param Data $helper
     * @param OrderRepositoryInterface $orderRepository
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $helper,
        OrderRepositoryInterface $orderRepository,
        array $data = []
    ) {
        $this->helper = $helper;
        $this->orderRepository = $orderRepository;
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
     * @return TrueLoyalInvoiceCreateDiscount
     */
    public function initTotals()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        $order = $this->orderRepository->get($orderId);
        $invoiceId = '';
        foreach ($order->getInvoiceCollection() as $invoice) {
            if ($invoice->getIncrementId()) {
                $invoiceId = $invoice->getIncrementId();
                break;
            }
        }
        $totalAmount = $this->helper->getRedeemRewardDiscountData($orderId);
        if (!$invoiceId) {
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
