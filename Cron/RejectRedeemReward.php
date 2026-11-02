<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Cron;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use TrueLoyal\LoyaltyRewards\Helper\Data;
use TrueLoyal\LoyaltyRewards\Logger\Logger;
use TrueLoyal\LoyaltyRewards\Model\Event\Cart\AbandonedCartPayload;
use TrueLoyal\LoyaltyRewards\Model\Event\EventSender;
use TrueLoyal\LoyaltyRewards\Model\MemberIdResolver;
use TrueLoyal\LoyaltyRewards\Model\ModuleState;
use TrueLoyal\LoyaltyRewards\Model\ResourceModel\TrueLoyalQuote\CollectionFactory as TrueLoyalQuoteCollectionFactory;
use TrueLoyal\LoyaltyRewards\Model\Settings;

/**
 * Every minute: for customers' carts idle longer than abandoned_cart_time, reject the pending redemption and send
 * cart_abandonment (once per armed quote, with the configuration of the quote's store)
 */
class RejectRedeemReward
{
    public const COLLECTION_LIMIT = 100;
    /** trueloyal_quote.is_abandoned_cart_sent: armed by a cart change, sent */
    private const ARMED = 2;
    private const SENT = 1;

    public function __construct(
        private readonly QuoteCollectionFactory $quoteCollectionFactory,
        private readonly TrueLoyalQuoteCollectionFactory $trueloyalQuoteCollectionFactory,
        private readonly Data $helper,
        private readonly ModuleState $moduleState,
        private readonly Settings $settings,
        private readonly MemberIdResolver $memberIdResolver,
        private readonly AbandonedCartPayload $payload,
        private readonly EventSender $sender,
        private readonly Logger $logger
    ) {
    }

    public function execute(): void
    {
        try {
            $quotes = $this->getArmedQuotes();
        } catch (\Throwable $e) {
            $this->logger->addErrorLog($e->getMessage());
            return;
        }
        foreach ($quotes as $quote) {
            /* One failing quote must not stop the others or fail the cron job */
            try {
                $storeId = $quote->getStoreId();
                if (!$this->moduleState->isEventEnabled('cart_abandonment', $storeId)
                    || !$this->isAbandoned((string)$quote->getUpdatedAt(), $storeId)
                    || !$quote->getItemsCount()
                ) {
                    continue;
                }
                if ($this->helper->getTrueLoyalQuoteByQuoteId($quote->getId())->getRedeemRewardDiscount()) {
                    $this->helper->sendRejectRewardRequest($quote);
                }
                $memberId = $this->memberIdResolver->forCustomer(
                    $quote->getCustomerId(),
                    $quote->getCustomer() ? (string)$quote->getCustomer()->getEmail() : '',
                    $storeId
                );
                $this->sender->send('cart_abandonment', $memberId, $this->payload->build($quote), $storeId);
                $this->helper->getTrueLoyalQuoteByQuoteId($quote->getId())->setIsAbandonedCartSent(self::SENT)->save();
            } catch (\Throwable $e) {
                $this->logger->addErrorLog('Abandoned cart, quote ' . $quote->getId() . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Active customer quotes armed for the abandoned-cart event, newest first
     *
     * @return Quote[]
     */
    private function getArmedQuotes(): array
    {
        $quoteIds = $this->trueloyalQuoteCollectionFactory->create()
            ->addFieldToFilter('is_abandoned_cart_sent', ['eq' => self::ARMED])
            ->getColumnValues('quote_id');
        if (!$quoteIds) {
            return [];
        }
        $collection = $this->quoteCollectionFactory->create()
            ->addFieldToFilter('is_active', ['eq' => '1'])
            ->addFieldToFilter('customer_id', ['neq' => null])
            ->addFieldToFilter('entity_id', ['in' => $quoteIds])
            ->setOrder('created_at', 'desc');
        $collection->getSelect()->limit(self::COLLECTION_LIMIT);
        return $collection->getItems();
    }

    /**
     * Whether the quote has been idle for abandoned_cart_time minutes (updated_at is UTC)
     */
    private function isAbandoned(string $updatedAt, int|string|null $storeId): bool
    {
        $minutes = (int)$this->settings->getValue(Settings::XML_PATH_ABANDONED_CART_TIME, $storeId);
        $updated = new \DateTime($updatedAt, new \DateTimeZone('UTC'));
        return $updated->getTimestamp() + $minutes * 60 <= time();
    }
}
