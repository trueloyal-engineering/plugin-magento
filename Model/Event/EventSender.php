<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\Event;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderInterface;
use TrueLoyal\LoyaltyRewards\Logger\Logger;
use TrueLoyal\LoyaltyRewards\Model\Client\EventPublisher;
use TrueLoyal\LoyaltyRewards\Model\MemberIdResolver;
use TrueLoyal\LoyaltyRewards\Model\Settings;

/**
 * Sends one activity event, {member_id, activity_id, data}, to the merchant's TrueLoyal workflow
 *
 * Delivery problems are logged, never thrown: an event must not break the save that triggered it.
 */
class EventSender
{
    public function __construct(
        private readonly Settings $settings,
        private readonly MemberIdResolver $memberIdResolver,
        private readonly EventPublisher $publisher,
        private readonly Json $json,
        private readonly Logger $logger
    ) {
    }

    public function send(string $activityId, string $memberId, array $data): void
    {
        $body = $this->json->serialize(['member_id' => $memberId, 'activity_id' => $activityId, 'data' => $data]);
        try {
            $this->publisher->send($this->settings->getWebhookUrl(), $body);
        } catch (\Exception $e) {
            $this->logger->addErrorLog($e->getMessage());
        }
    }

    /**
     * Send an event on behalf of the order's customer (or guest)
     */
    public function sendForOrder(string $activityId, OrderInterface $order, array $data): void
    {
        $memberId = $this->memberIdResolver->forCustomer($order->getCustomerId(), $order->getCustomerEmail());
        $this->send($activityId, $memberId, $data);
    }
}
