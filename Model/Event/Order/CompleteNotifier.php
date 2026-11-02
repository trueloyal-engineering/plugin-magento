<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\Event\Order;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use TrueLoyal\LoyaltyRewards\Helper\Config;
use TrueLoyal\LoyaltyRewards\Logger\Logger;
use TrueLoyal\LoyaltyRewards\Model\Event\EventSender;
use TrueLoyal\LoyaltyRewards\Model\ModuleState;

/**
 * order_complete, once per order, when an invoice or shipment completes it
 */
class CompleteNotifier
{
    public function __construct(
        private readonly ModuleState $moduleState,
        private readonly Config $config,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderCompletePayload $payload,
        private readonly EventSender $sender,
        private readonly Logger $logger
    ) {
    }

    public function notifyIfComplete(Order $order): void
    {
        $trueloyalOrder = $this->config->getTrueLoyalOrderByOrderId($order->getEntityId());
        if (!$this->moduleState->isEventEnabled('order_complete')
            || $order->getState() != Order::STATE_COMPLETE
            || $trueloyalOrder->getCompleteRequestSent() != 0
        ) {
            return;
        }
        /** @var Order $order */
        $order = $this->orderRepository->get($order->getEntityId());
        $this->sender->sendForOrder('order_complete', $order, $this->payload->build($order));
        try {
            $trueloyalOrder->setCompleteRequestSent(1)->save();
        } catch (CouldNotSaveException $e) {
            $this->logger->addErrorLog($e->getMessage());
        }
    }
}
