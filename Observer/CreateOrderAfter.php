<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use TrueLoyal\LoyaltyRewards\Helper\Config;
use TrueLoyal\LoyaltyRewards\Logger\Logger;
use TrueLoyal\LoyaltyRewards\Model\Client\LiveApi;
use TrueLoyal\LoyaltyRewards\Model\Event\EventSender;
use TrueLoyal\LoyaltyRewards\Model\Event\Order\OrderCreatePayload;
use TrueLoyal\LoyaltyRewards\Model\ModuleState;

/**
 * Once per order, from every channel (sales_order_save_commit_after of an order MarkPlacedOrder flagged): approve the
 * redeemed reward, record it on the order and send order_create
 */
class CreateOrderAfter implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly SerializerInterface $serializer,
        private readonly LiveApi $liveApi,
        private readonly OrderCreatePayload $payload,
        private readonly EventSender $sender,
        private readonly ModuleState $moduleState,
        private readonly Logger $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order->getData(MarkPlacedOrder::FLAG)) {
            return;
        }
        $order->unsetData(MarkPlacedOrder::FLAG);
        $orderId = $order->getId();
        $trueloyalOrder = $this->config->getTrueLoyalOrderByOrderId($orderId);
        if (!$trueloyalOrder->getData('trueloyal_reward')) {
            $trueloyalOrder->setData('trueloyal_reward', $this->approveRedeemedReward($order))->setOrderId($orderId);
            try {
                $trueloyalOrder->save();
            } catch (CouldNotSaveException $e) {
                $this->logger->addErrorLog($e->getMessage());
            }
        }
        // Built even when order_create is off: it also records each item's share of the reward discount
        $data = $this->payload->build($orderId);
        if ($this->moduleState->isEventEnabled('order_create')) {
            $this->sender->sendForOrder('order_create', $order, $data);
        }
    }

    /**
     * Approve the pending redemption of the order's quote
     *
     * @return string the redeemed reward as JSON, '{}' without one
     */
    private function approveRedeemedReward($order): string
    {
        $quoteId = $order->getQuoteId();
        if ($quoteId === null) {
            return '{}';
        }
        $quote = $this->quoteRepository->get($quoteId);
        $rewardId = $this->config->getTrueLoyalQuoteByQuoteId($quoteId)->getRedeemRewardDiscount();
        if (!$rewardId) {
            return '{}';
        }
        $reward = $this->config->getRewardRulesData($quote, $rewardId);
        try {
            $this->liveApi->request('post', $this->liveApi->transactionUrl($reward['id'] . '/approve'));
        } catch (\Exception $e) {
            $this->logger->addErrorLog($e->getMessage());
        }
        return $this->serializer->serialize($reward);
    }
}
