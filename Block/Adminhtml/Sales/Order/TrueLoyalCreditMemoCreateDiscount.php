<?php

namespace TrueLoyal\LoyaltyRewards\Block\Adminhtml\Sales\Order;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class TrueLoyalCreditMemoCreateDiscount extends Template
{
    /**
     * @var Data
     */
    private $helper;
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * TrueLoyal Credit MemoCreate Discount constructor.
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
        $this->orderRepository = $orderRepository;
        $this->helper = $helper;
        parent::__construct(
            $context,
            $data
        );
    }

    /**
     * This function call initTotals
     *
     * @return TrueLoyalCreditMemoCreateDiscount
     */
    public function initTotals()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        $order = $this->orderRepository->get($orderId);
        $creditMemoId = '';
        foreach ($order->getCreditmemosCollection() as $creditMemo) {
            if ($creditMemo->getIncrementId()) {
                $creditMemoId = $creditMemo->getIncrementId();
                break;
            }
        }
        $totalAmount = $this->helper->getRedeemRewardDiscountData($orderId);
        if (!$creditMemoId) {
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
     * This function call Order
     *
     * @return mixed
     */
    public function getOrder()
    {
        return $this->getParentBlock()->getOrder();
    }
}
