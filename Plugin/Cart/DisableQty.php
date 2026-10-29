<?php

namespace TrueLoyal\LoyaltyRewards\Plugin\Cart;

use Magento\Checkout\Block\Cart\AbstractCart;
use Magento\Checkout\Model\Session as CheckoutSession;
use TrueLoyal\LoyaltyRewards\Helper\Config as TrueLoyalHelper;

class DisableQty
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;
    /**
     * @var TrueLoyalHelper
     */
    private $trueloyalHelper;

    /**
     * Disable Qty for Free product
     *
     * @param CheckoutSession $checkoutSession
     * @param TrueLoyalHelper $trueloyalHelper
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        TrueLoyalHelper $trueloyalHelper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->trueloyalHelper = $trueloyalHelper;
    }

    /**
     * After Get ItemRenderer
     *
     * @param AbstractCart $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterGetItemRenderer(AbstractCart $subject, $result)
    {
        $isFreeProduct = false;
        $quote = $this->checkoutSession->getQuote();
        $items = $quote->getAllItems();
        foreach ($items as $item) {
            $trueloyalQuoteItem = $this->trueloyalHelper->getTrueLoyalQuoteItemByItemId($item->getId());
            if ($trueloyalQuoteItem->getIsTrueLoyalFreeProduct()) {
                $isFreeProduct = true;
            }
        }
        if ($isFreeProduct) {
            $result->setTemplate('TrueLoyal_LoyaltyRewards::cart/item/default.phtml');
        }
        return $result;
    }
}
