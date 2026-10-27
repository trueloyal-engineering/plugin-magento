<?php

namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Flags an order as just placed (storefront, REST, GraphQL, multi-address checkout or admin), so CreateOrderAfter
 * handles it on its first save only: later saves (invoice, shipment, cancel, refund) must not re-send order_create,
 * re-approve the redemption or add the TrueLoyal discount to the items again.
 */
class MarkPlacedOrder implements ObserverInterface
{
    public const FLAG = 'trueloyal_placed_order';

    /**
     * Execute
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $observer->getEvent()->getOrder()->setData(self::FLAG, true);
    }
}
