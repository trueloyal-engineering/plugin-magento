<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use TrueLoyal\LoyaltyRewards\Model\Event\Customer\CustomerSync;

/**
 * customer_save_before, customer_save_commit_after, customer_address_save_commit_after,
 * customer_address_delete_commit_after: record the customer for customer_create / customer_update (sent once at the
 * end of the request)
 *
 * Model events, so every channel that saves through the customer models is covered: storefront, admin, REST,
 * GraphQL and integrations.
 */
class CustomerSaveAfter implements ObserverInterface
{
    public function __construct(private readonly CustomerSync $customerSync)
    {
    }

    public function execute(Observer $observer): void
    {
        $customer = $observer->getEvent()->getCustomer();
        if ($customer && $observer->getEvent()->getName() === 'customer_save_before') {
            $this->customerSync->beforeSave($customer);
            return;
        }
        if ($customer) {
            $this->customerSync->afterSave($customer);
            return;
        }
        $address = $observer->getEvent()->getCustomerAddress();
        if ($address) {
            $this->customerSync->add((int)($address->getCustomerId() ?: $address->getParentId()));
        }
    }
}
