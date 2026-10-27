<?php

namespace TrueLoyal\LoyaltyRewards\Cron;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Quote\Model\QuoteFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use TrueLoyal\LoyaltyRewards\Helper\Data;
use TrueLoyal\LoyaltyRewards\Logger\Logger;

class RejectRedeemReward
{
    public const COLLECTION_LIMIT = 100;
    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var Logger
     */
    private $trueloyalLogger;
    /**
     * @var int minutes
     */
    private $abandonedCartTime;

    /**
     * AbandonedCart constructor.
     *
     * @param QuoteFactory $quoteFactory
     * @param Data $helper
     * @param Logger $trueloyalLogger
     */
    public function __construct(
        QuoteFactory $quoteFactory,
        Data $helper,
        Logger $trueloyalLogger
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->helper = $helper;
        $this->trueloyalLogger = $trueloyalLogger;
    }

    /**
     * Send abandoned cart request to TrueLoyal and Reject redeemed point
     *
     * @return void
     */
    public function execute()
    {
        /** Get all customer's active cart data*/
        $event = $this->helper->getRewardEvents();
        if (!in_array('cart_abandonment', $event, true)) {
            return;
        }
        $url = $this->helper->getWebHookUrl();
        $this->abandonedCartTime = (int)$this->helper->getAbandonedCartTime();

        try {
            $quoteList = $this->getQuoteCollections()->getItems();
        } catch (\Throwable $e) {
            $this->trueloyalLogger->addErrorLog($e->getMessage());
            return;
        }
        foreach ($quoteList as $item) {
            /* One failing quote must not stop the others or fail the cron job */
            try {
                $quoteObj = clone $item;
                if ($this->isAbandoned($item->getUpdatedAt()) && $item->getItemsCount()) {
                    /* Send reject reward request to Live API
                    We have to request event customer Quote wise so need to send an API one by one.*/
                    $trueloyalQuote = $this->helper->getTrueLoyalQuoteByQuoteId($item->getId());
                    if ($trueloyalQuote->getRedeemRewardDiscount()) {
                        $this->helper->sendRejectRewardRequest($item);
                    }
                    /*Send Cart abandonment request custom event API*/
                    $itemsValues = [];
                    foreach ($item->getItemsCollection() as $quoteItem) {
                        $quoteItem->setData('product_url', $quoteItem->getProduct()->getProductUrl());
                        $quoteItem->setData('product_image_url', $this->helper
                            ->getImageFullUrl($quoteItem->getProduct()));
                        $quoteItem->setData('category_name', $this->helper
                            ->getCategoryName($quoteItem->getProduct()->getEntityId()));
                        $quoteItem->unsetData('stock_state_result');
                        $quoteItem->unsetData('product');
                        $itemsValues[] = $quoteItem->debug();
                    }
                    $quoteData = $item->debug();
                    $quoteData = $this->helper->setFormatedPrice($quoteData);
                    $quoteData['items_qty'] = (int)$quoteData['items_qty'];
                    $quoteData["coupon_code"] = $item->getCouponCode() ? [$item->getCouponCode()] : [];
                    $itemData = $itemsValues;
                    foreach ($itemData as $key => $qItem) {
                        $qItem['qty'] = (int)$qItem['qty'];
                        $qItem = $this->helper->setFormatedPrice($qItem);
                        $quoteData["items"][] = $qItem;
                    }
                    $memberId = $this->helper->getMemberIdentifierValueById(
                        $item->getCustomerId(),
                        $item->getCustomer() ? $item->getCustomer()->getEmail() : ""
                    );
                    $this->sendToTrueLoyal($memberId, $quoteData, $url, $quoteObj);
                }
            } catch (\Throwable $e) {
                $this->trueloyalLogger->addErrorLog('Abandoned cart, quote ' . $item->getId() . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Get Quote Collections
     *
     * @return AbstractDb|AbstractCollection|null
     */
    public function getQuoteCollections()
    {
        try {
            $trueloyalQuote = $this->helper->trueloyalQuoteFactory->create()
                ->getCollection()
                ->addFieldToSelect(['quote_id'])
                ->addFieldToFilter('is_abandoned_cart_sent', ['eq' => '2']);
            $quoteIds = [];
            foreach ($trueloyalQuote as $quote) {
                $quoteIds[] = $quote->getQuoteId();
            }
            $quote = $this->quoteFactory->create()
                ->getCollection()
                ->addFieldToFilter('is_active', ['eq' => '1'])
                ->addFieldToFilter('customer_id', ['neq' => null])
                ->addFieldToFilter('entity_id', ['in' => $quoteIds])
                ->setOrder(
                    'created_at',
                    'desc'
                );
            $quote->getSelect()->limit(self::COLLECTION_LIMIT);
            return $quote;
        } catch (\Throwable $e) {
            $this->trueloyalLogger->addErrorLog($e->getMessage());
        }
    }

    /**
     * Whether the quote has been idle for longer than the abandoned cart time.
     * Compared as timestamps: quote updated_at is stored in UTC, whatever the store timezone.
     *
     * @param string $quoteTime
     * @return bool
     */
    public function isAbandoned($quoteTime)
    {
        $updatedAt = new \DateTime((string)$quoteTime, new \DateTimeZone('UTC'));
        return $updatedAt->getTimestamp() + $this->abandonedCartTime * 60 <= time();
    }

    /**
     * Send To TrueLoyal
     *
     * @param mixed $memberId
     * @param mixed $quoteData
     * @param mixed $url
     * @param mixed $object
     */
    public function sendToTrueLoyal($memberId, $quoteData, $url, $object)
    {
        try {
            $params = [
                "member_id" => $memberId,
                "activity_id" => "cart_abandonment",
                "data" => $quoteData
            ];
            $params = $this->helper->json->serialize($params);
            /*We have to request event customer Quote wise so need to send an API one by one.*/
            $this->helper->request($url, $params, "post");
            $trueloyalQuote = $this->helper->getTrueLoyalQuoteByQuoteId($object->getId());
            $trueloyalQuote->setIsAbandonedCartSent(1);
            $trueloyalQuote->save();
        } catch (\Throwable $e) {
            $this->trueloyalLogger->addErrorLog($e->getMessage());
        }
    }
}
