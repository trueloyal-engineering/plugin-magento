<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;
use TrueLoyal\LoyaltyRewards\Helper\Config;
use TrueLoyal\LoyaltyRewards\Logger\Logger;
use TrueLoyal\LoyaltyRewards\Model\Event\EventSender;
use TrueLoyal\LoyaltyRewards\Model\Event\Review\ReviewPayload;
use TrueLoyal\LoyaltyRewards\Model\MemberIdResolver;
use TrueLoyal\LoyaltyRewards\Model\ModuleState;

/**
 * review_save_commit_after: review_submitted once per pending review, review_approved on each approval
 */
class ProductReviewSaveAfter implements ObserverInterface
{
    public function __construct(
        private readonly ModuleState $moduleState,
        private readonly ReviewFactory $reviewFactory,
        private readonly Config $config,
        private readonly ReviewPayload $payload,
        private readonly MemberIdResolver $memberIdResolver,
        private readonly EventSender $sender,
        private readonly Logger $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $saved = $observer->getEvent()->getObject();
        $reviewId = $saved->getData('review_id');
        $statusId = $saved->getData('status_id');
        $review = $this->reviewFactory->create()->load($reviewId);
        $storeId = $review->getStoreId() ?: $saved->getData('store_id');
        $trueloyalReview = $this->config->getTrueLoyalReviewByReviewId($reviewId);
        if ($statusId == Review::STATUS_APPROVED && $this->moduleState->isEventEnabled('review_approved', $storeId)) {
            $this->send('review_approved', $saved, $review, $storeId);
        } elseif ($statusId == Review::STATUS_PENDING
            && $this->moduleState->isEventEnabled('review_submitted', $storeId)
            && !$trueloyalReview->getData('submitted_to_trueloyal')
        ) {
            $this->send('review_submitted', $saved, $review, $storeId);
            try {
                $trueloyalReview->setData('submitted_to_trueloyal', 1)->setReviewId($reviewId)->save();
            } catch (CouldNotSaveException $e) {
                $this->logger->addErrorLog($e->getMessage());
            }
        }
    }

    private function send(string $activityId, $saved, Review $review, int|string|null $storeId): void
    {
        $memberId = $this->memberIdResolver->forCustomer($saved->getData('customer_id'), null, $storeId);
        $data = $this->payload->build($review, $saved->getData('entity_pk_value'));
        $this->sender->send($activityId, $memberId, $data, $storeId);
    }
}
