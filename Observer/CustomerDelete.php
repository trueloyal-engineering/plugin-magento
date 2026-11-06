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
     * @var Data
     */
    private $helper;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * Customer Delete constructor.
     *
     * @param Data $helper
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        Data $helper,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->helper = $helper;
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
        $deletedCustomer = $observer->getEvent()->getCustomer();
        /*Settings of the customer's website: in the admin and the API the current store is another one*/
        return $this->helper->runInStoreScope(
            $this->helper->getCustomerScopeStoreId($deletedCustomer),
            fn () => $this->sendCustomerDelete($deletedCustomer)
        );
    }

    /**
     * Send the customer delete event, when enabled
     *
     * @param \Magento\Customer\Model\Customer $deletedCustomer
     * @return bool|void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function sendCustomerDelete($deletedCustomer)
    {
        $event = $this->helper->getRewardEvents();
        /*Check the customer_delete event is enabled, if enabled then will send customer_delete event to TrueLoyal*/
        if (in_array('customer_delete', $event)) {
            $customerId = $deletedCustomer->getEntityId();
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
