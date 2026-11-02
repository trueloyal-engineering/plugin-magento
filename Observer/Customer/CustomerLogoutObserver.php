<?php
namespace TrueLoyal\LoyaltyRewards\Observer\Customer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class CustomerLogoutObserver implements ObserverInterface
{

    /**
     * CustomerLogoutObserver constructor.
     *
     * @param Data $helper
     */
    public function __construct(
        private readonly Data $helper
    ) {
    }

    /**
     * Execute
     *
     * @param Observer $observer
     */
    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
        $this->helper->deleteCookie();
    }
}
