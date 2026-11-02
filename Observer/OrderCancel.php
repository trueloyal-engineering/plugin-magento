<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use TrueLoyal\LoyaltyRewards\Model\Event\EventSender;
use TrueLoyal\LoyaltyRewards\Model\Event\Order\OrderCancelPayload;
use TrueLoyal\LoyaltyRewards\Model\ModuleState;

/**
 * order_cancel_after: send order_cancel (admin event option order_cancelled)
 */
class OrderCancel implements ObserverInterface
{
    public function __construct(
        private readonly ModuleState $moduleState,
        private readonly OrderCancelPayload $payload,
        private readonly EventSender $sender
    ) {
    }

    public function execute(Observer $observer): void
    {
        if ($this->moduleState->isEventEnabled('order_cancelled')) {
            $order = $observer->getEvent()->getOrder();
            $this->sender->sendForOrder('order_cancel', $order, $this->payload->build($order));
        }
    }
}
