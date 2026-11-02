<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use TrueLoyal\LoyaltyRewards\Model\Event\EventSender;
use TrueLoyal\LoyaltyRewards\Model\Event\Order\CompleteNotifier;
use TrueLoyal\LoyaltyRewards\Model\Event\Order\OrderShippedPayload;
use TrueLoyal\LoyaltyRewards\Model\ModuleState;

/**
 * sales_order_shipment_save_commit_after: order_complete once complete, order_shipped once fully shipped
 */
class OrderShipped implements ObserverInterface
{
    public function __construct(
        private readonly ModuleState $moduleState,
        private readonly OrderShippedPayload $payload,
        private readonly EventSender $sender,
        private readonly CompleteNotifier $completeNotifier
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getShipment()->getOrder();
        $this->completeNotifier->notifyIfComplete($order);
        if ($this->moduleState->isEventEnabled('order_shipped') && !$order->canShip()) {
            try {
                $this->sender->sendForOrder('order_shipped', $order, $this->payload->build($order));
            } catch (\Exception) {
                return;
            }
        }
    }
}
