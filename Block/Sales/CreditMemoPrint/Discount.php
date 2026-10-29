<?php

namespace TrueLoyal\LoyaltyRewards\Block\Sales\CreditMemoPrint;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class Discount extends Template
{
    /**
     * @var Order
     */
    protected $_order;

    /**
     * @var Source
     */
    protected $_source;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var CreditmemoRepositoryInterface
     */
    private $creditmemoRepository;

    /**
     * CreditMemo Print Discount constructor.
     *
     * @param Context $context
     * @param Data $helper
     * @param CreditmemoRepositoryInterface $creditMemoRepository
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $helper,
        CreditmemoRepositoryInterface $creditMemoRepository,
        array $data = []
    ) {
        $this->helper = $helper;
        $this->creditmemoRepository = $creditMemoRepository;
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
        return $this->_source;
    }

    /**
     * This function call Order
     *
     * @return Order
     */
    public function getOrder()
    {
        return $this->_order;
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
     * @return Discount
     */
    public function initTotals()
    {
        $creditMemoId = $this->getRequest()->getParam('creditmemo_id');
        if ($creditMemoId) {
            $orderId = $this->creditmemoRepository->get($creditMemoId)->getOrderId();
        } else {
            $orderId = $this->getRequest()->getParam('order_id');
        }
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
        return $this->_order->getStore();
    }
}
