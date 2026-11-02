<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use TrueLoyal\LoyaltyRewards\Logger\Logger;
use TrueLoyal\LoyaltyRewards\Model\Event\EventSender;
use TrueLoyal\LoyaltyRewards\Model\Event\Order\CompleteNotifier;
use TrueLoyal\LoyaltyRewards\Model\Event\Order\OrderPaidPayload;
use TrueLoyal\LoyaltyRewards\Model\ModuleState;

/**
 * sales_order_invoice_save_commit_after: order_paid once the order is fully invoiced, order_complete once complete
 *
 * The order comes from the saved invoice, so online captures, cron and API invoices are covered too.
 */
class OrderFullInvoicePaid implements ObserverInterface
{
    public function __construct(
        private readonly ModuleState $moduleState,
        private readonly OrderPaidPayload $payload,
        private readonly EventSender $sender,
        private readonly CompleteNotifier $completeNotifier,
        private readonly Logger $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getInvoice()->getOrder();
        if ($this->moduleState->isEventEnabled('order_paid') && !$order->canInvoice()) {
            try {
                $this->sender->sendForOrder('order_paid', $order, $this->payload->build($order));
            } catch (\Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
        $this->completeNotifier->notifyIfComplete($order);
    }
}
