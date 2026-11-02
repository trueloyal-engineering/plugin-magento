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

    public function isEnabled(): bool
    {
        if (!$this->settings->isActive()) {
            return false;
        }
        if (!$this->settings->isAutoEnrollmentEnabled()) {
            $customerId = $this->customerSessionFactory->create()->getCustomerId();
            if ($customerId && !$this->getOptInValue((int)$customerId)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Value of the customer's opt-in attribute (Settings::getOptInAttributeCode()), null when not set
     */
    public function getOptInValue(int $customerId): mixed
    {
        $attribute = $this->customerRepository->getById($customerId)
            ->getCustomAttribute($this->settings->getOptInAttributeCode());
        return $attribute?->getValue();
    }

    /**
     * Selected activity events, or none while the integration is off for this request
     *
     * @return string[]
     */
    public function getRewardEvents(): array
    {
        $events = $this->settings->getRewardEvents();
        return $events && $this->isEnabled() ? $events : [];
    }

    /**
     * @param string $event an option of the admin's event list, e.g. 'order_create', 'order_refund'
     */
    public function isEventEnabled(string $event): bool
    {
        return in_array($event, $this->getRewardEvents(), true);
    }
}
