<?php
namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class ProductReviewSaveAfter implements ObserverInterface
{
    /**
     * @var Data
     */
    private $helper;
    /**
     * @var ReviewFactory
     */
    private $reviewFactory;

    /**
     * Product review constructor.
     *
     * @param Data $helper
     * @param ReviewFactory $reviewFactory
     */
    public function __construct(
        Data $helper,
        ReviewFactory $reviewFactory
    ) {
        $this->helper = $helper;
        $this->reviewFactory = $reviewFactory;
    }

    /**
     * Product review create/approve event to TrueLoyal
     *
     * @param Observer $observer
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
        $event = $this->helper->getRewardEvents();
        $reviewObject = $observer->getEvent()->getObject();
        $review = $reviewObject->getData();
        $customerId = $review["customer_id"];
        $productId = $review["entity_pk_value"];
        $reviewId = $review["review_id"];
        $statusId = $review["status_id"];
        $review = $this->reviewFactory->create()->load($reviewId);
        $reviewData = $review->toArray();
        $trueloyalReview = $this->helper->getTrueLoyalReviewByReviewId($reviewId);
        if ($statusId == Review::STATUS_APPROVED && in_array('review_approved', $event, true)) {
            $this->sendRequest($reviewData, $productId, $customerId, "review_approved");
        } elseif ($statusId == Review::STATUS_PENDING &&
            in_array('review_submitted', $event, true) &&
            !$trueloyalReview->getSubmittedToTrueLoyal()
        ) {
            $this->sendRequest($reviewData, $productId, $customerId, "review_submitted");
            try {
                $trueloyalReview->setSubmittedToTrueLoyal(1)->setReviewId($reviewId)->save();
            } catch (CouldNotSaveException $e) {
                $this->helper->addErrorLog($e->getMessage());
            }
        }
        return true;
    }

    /**
     * Send review created - approved event to TrueLoyal
     *
     * @param array $reviewData
     * @param int $productId
     * @param int $customerId
     * @param string $activityId
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function sendRequest($reviewData, $productId, $customerId, $activityId): bool
    {
        /*Product url and product image url not availale from Review so we have to load product to get an additional required data*/
        $productInfo = $this->helper->getProductUrlAndImageUrl($productId);
        $reviewData['product_url'] = $productInfo['product_url'];
        $reviewData['product_image_url'] = $productInfo['product_image_url'];
        $reviewData['category_name'] = $this->helper->getCategoryName($productId);
        $params = [
            "member_id" => $this->helper->getMemberIdentifierValueById($customerId),
            "activity_id" => $activityId,
            "data" => $reviewData
        ];
        $url = $this->helper->getWebHookUrl();
        $params = $this->helper->json->serialize($params);
        $this->helper->request($url, $params, "post");
        return true;
    }
}
