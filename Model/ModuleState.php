<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\SessionFactory;

/**
 * Whether the integration is on for the current request, and which activity events are selected
 *
 * With auto-enrollment off, a logged-in customer who hasn't opted in turns the integration off for their own requests.
 */
class ModuleState
{
    public function __construct(
        private readonly Settings $settings,
        private readonly SessionFactory $customerSessionFactory,
        private readonly CustomerRepositoryInterface $customerRepository
    ) {
    }

    /**
     * @param int|string|null $storeId the store the event belongs to (an order's, a customer's), default the current
     */
    public function isEnabled(int|string|null $storeId = null): bool
    {
        if (!$this->settings->isActive($storeId)) {
            return false;
        }
        if (!$this->settings->isAutoEnrollmentEnabled($storeId)) {
            $customerId = $this->customerSessionFactory->create()->getCustomerId();
            if ($customerId && !$this->getOptInValue((int)$customerId, $storeId)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Value of the customer's opt-in attribute (Settings::getOptInAttributeCode()), null when not set
     */
    public function getOptInValue(int $customerId, int|string|null $storeId = null): mixed
    {
        $attribute = $this->customerRepository->getById($customerId)
            ->getCustomAttribute($this->settings->getOptInAttributeCode($storeId));
        return $attribute?->getValue();
    }

    /**
     * Selected activity events, or none while the integration is off for this request
     *
     * @return string[]
     */
    public function getRewardEvents(int|string|null $storeId = null): array
    {
        $events = $this->settings->getRewardEvents($storeId);
        return $events && $this->isEnabled($storeId) ? $events : [];
    }

    /**
     * @param string $event an option of the admin's event list, e.g. 'order_create', 'order_refund'
     */
    public function isEventEnabled(string $event, int|string|null $storeId = null): bool
    {
        return in_array($event, $this->getRewardEvents($storeId), true);
    }
}
