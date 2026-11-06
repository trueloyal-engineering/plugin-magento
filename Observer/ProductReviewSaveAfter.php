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
        $reviewObject = $observer->getEvent()->getObject();
        $review = $reviewObject->getData();
        $customerId = $review["customer_id"];
        $productId = $review["entity_pk_value"];
        $reviewId = $review["review_id"];
        $statusId = $review["status_id"];
        $review = $this->reviewFactory->create()->load($reviewId);
        $reviewData = $review->toArray();
        $trueloyalReview = $this->helper->getTrueLoyalReviewByReviewId($reviewId);
        /*Settings of the review's store: in the admin and the API the current store is another one*/
        $scopeStoreId = $this->getScopeStoreId($reviewData, $customerId);
        return $this->helper->runInStoreScope(
            $scopeStoreId,
            function () use ($reviewData, $productId, $customerId, $reviewId, $statusId, $trueloyalReview) {
                $event = $this->helper->getRewardEvents();
                if ($statusId == Review::STATUS_APPROVED && in_array('review_approved', $event, true)) {
                    $this->sendRequest($reviewData, $productId, $customerId, "review_approved");
                } elseif ($statusId == Review::STATUS_PENDING &&
                    in_array('review_submitted', $event, true) &&
                    !$trueloyalReview->getData('submitted_to_trueloyal')
                ) {
                    $this->sendRequest($reviewData, $productId, $customerId, "review_submitted");
                    try {
                        $trueloyalReview->setData('submitted_to_trueloyal', 1)->setReviewId($reviewId)->save();
                    } catch (CouldNotSaveException $e) {
                        $this->helper->logger->addErrorLog($e->getMessage());
                    }
                }
                return true;
            }
        );
    }

    /**
     * Store whose settings apply to the review: the store it was written in, else the reviewer's
     *
     * @param array $reviewData
     * @param mixed $customerId
     * @return int|null
     */
    private function getScopeStoreId(array $reviewData, $customerId): ?int
    {
        $storeId = $this->helper->getReviewScopeStoreId($reviewData);
        if ($storeId === null && $customerId) {
            $customer = $this->helper->getCustomerById($customerId);
            $storeId = $customer ? $this->helper->getCustomerScopeStoreId($customer) : null;
        }
        return $storeId;
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
