<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\OrderFactory;
use TrueLoyal\LoyaltyRewards\Model\Event\EventSender;
use TrueLoyal\LoyaltyRewards\Model\Event\Order\RefundPayload;
use TrueLoyal\LoyaltyRewards\Model\ModuleState;

/**
 * sales_order_creditmemo_save_commit_after: full_order_refund when one credit memo covers the whole order,
 * partial_order_refund otherwise (admin event option order_refund)
 */
class OrderRefund implements ObserverInterface
{
    public function __construct(
        private readonly ModuleState $moduleState,
        private readonly OrderFactory $orderFactory,
        private readonly RefundPayload $payload,
        private readonly EventSender $sender
    ) {
    }

    public function execute(Observer $observer): void
    {
        $creditmemo = $observer->getEvent()->getCreditmemo();
        if (!$this->moduleState->isEventEnabled('order_refund') || !($creditmemo->getTotalQty() > 0)) {
            return;
        }
        try {
            $order = $this->orderFactory->create()->load($creditmemo->getOrderId());
            if ($this->payload->isFullRefund($order)) {
                $this->sender->sendForOrder('full_order_refund', $order, $this->payload->full($order));
            } else {
                $this->sender->sendForOrder('partial_order_refund', $order, $this->payload->partial($creditmemo, $order));
            }
        } catch (\Exception) {
            return;
        }
    }
}
