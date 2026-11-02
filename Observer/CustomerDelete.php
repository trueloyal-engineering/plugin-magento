<?php

namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class CustomerDelete implements ObserverInterface
{
    /**
     * Customer Delete constructor.
     *
     * @param Data $helper
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        private readonly Data $helper,
        private readonly CustomerRepositoryInterface $customerRepository
    ) {
    }

    /**
     * Send customer delete event to trueloyal
     *
     * @param Observer $observer
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $event = $this->helper->getRewardEvents();
        /*Check the customer_delete event is enabled, if enabled then will send customer_delete event to TrueLoyal*/
        if (in_array('customer_delete', $event)) {
            $customerId = $observer->getEvent()->getCustomer()->getEntityId();
            $customer = $this->customerRepository->getById($customerId);
            $customerData = $customer->__toArray();
            $params = [
                "member_id" => $this->helper->getMemberIdentifierValueById($customerId, $customer->getEmail()),
                "activity_id" => "customer_delete",
                "data" => $customerData
            ];
            $url = $this->helper->getWebHookUrl();
            $params = $this->helper->json->serialize($params);
            $this->helper->request($url, $params, "post");
            return true;
        }
    }
}
