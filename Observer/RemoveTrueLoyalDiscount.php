<?php

namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class RemoveTrueLoyalDiscount implements ObserverInterface
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;
    /**
     * @var Data
     */
    private $helper;

    /**
     * Remove TrueLoyal Discount constructor.
     *
     * @param CheckoutSession $checkoutSession
     * @param Data $helper
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        Data $helper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->helper = $helper;
    }

    /**
     * Remove Redeem Reward Discount
     *
     * @param Observer $observer
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $quote = $this->checkoutSession->getQuote();
        $trueloyalQuote = $this->helper->getTrueLoyalQuoteByQuoteId($quote->getId());
        if ($trueloyalQuote->getIsAbandonedCartSent() || $trueloyalQuote->getIsAbandonedCartSent() === null) {
            $this->helper->setAbandonedCartSent($quote->getId(), 2);
        }

        $cart = $observer->getEvent()->getCart();
        if ($cart->getItemsCount() > 1) {
            return true;
        } elseif ($cart->getItemsCount() === 1 && $trueloyalQuote->getRedeemRewardDiscount()) {
            foreach ($quote->getAllItems() as $item) {
                $trueloyalQuoteItem = $this->helper->getTrueLoyalQuoteItemByItemId($item->getId());
                if ($trueloyalQuoteItem->getIsTrueLoyalFreeProduct()) {
                    $this->helper->sendRejectRewardRequest($quote);
                    $quote->delete();
                    return true;
                }
            }
        } elseif ($cart->getItemsCount() === 0 && $trueloyalQuote->getRedeemRewardDiscount()) {
            $this->helper->sendRejectRewardRequest($quote);
            return true;
        }
    }
}
