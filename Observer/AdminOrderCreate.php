<?php

namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class AdminOrderCreate implements ObserverInterface
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * CustomPrice constructor.
     *
     * @param Data $helper
     */
    public function __construct(
        Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Order Create event to trueloyal
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $event = $this->helper->getRewardEvents();
        if (in_array('order_create', $event, true)) {
            $order = $observer->getEvent()->getOrder();
            $replacedOrderId = $this->helper->getReplacedOrderID($order->getEntityId(), false);
            return $this->helper->createTrueLoyalOrder($order->getId(), $replacedOrderId);
        }
    }
}
