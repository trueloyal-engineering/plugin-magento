<?php

namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class LockFreeProductQty implements ObserverInterface
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * Lock Free Product Qty constructor.
     *
     * @param Data $helper
     */
    public function __construct(
        Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Keep the qty of a redeemed free product at 1, whatever path (cart, minicart, REST, GraphQL) changed it
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $quote = $observer->getEvent()->getQuote();
        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($item->getId()) {
                $items[$item->getId()] = $item;
            }
        }
        foreach ($this->helper->getTrueLoyalFreeProductItemIds(array_keys($items)) as $itemId) {
            if ((float)$items[$itemId]->getQty() !== 1.0) {
                $items[$itemId]->setQty(1);
            }
        }
    }
}
