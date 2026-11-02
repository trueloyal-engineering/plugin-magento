<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\Event\Customer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerRegistry;
use TrueLoyal\LoyaltyRewards\Logger\Logger;
use TrueLoyal\LoyaltyRewards\Model\Event\EventSender;
use TrueLoyal\LoyaltyRewards\Model\MemberIdResolver;
use TrueLoyal\LoyaltyRewards\Model\ModuleState;

/**
 * customer_create / customer_update, sent once per customer per request
 *
 * A single request often saves a customer several times (sign-up saves it twice, the admin form and checkout save
 * every address separately). Each save only records the customer; the event goes out when the request ends, built
 * from the saved customer with all its addresses.
 */
class CustomerSync
{
    /** @var array<int, array{activity: string, enabled: bool}> */
    private array $pending = [];
    private bool $flushRegistered = false;
    private int $paused = 0;
    /** Customers being created by a save in progress (the EAV save clears isObjectNew() before save_after) */
    private \WeakMap $creating;

    public function __construct(
        private readonly ModuleState $moduleState,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerRegistry $customerRegistry,
        private readonly MemberIdResolver $memberIdResolver,
        private readonly EventSender $sender,
        private readonly Logger $logger
    ) {
        $this->creating = new \WeakMap();
    }

    /**
     * Call before a customer model is saved: remembers whether the save creates it
     */
    public function beforeSave(object $customer): void
    {
        if (!$customer->getId()) {
            $this->creating[$customer] = true;
        }
    }

    /**
     * Call after a customer model was saved
     */
    public function afterSave(object $customer): void
    {
        $isNew = isset($this->creating[$customer]);
        unset($this->creating[$customer]);
        $this->add((int)$customer->getId(), $isNew);
    }

    /**
     * Record that a customer (or one of their addresses) was saved
     *
     * @param bool $isNew the customer was created by this save: customer_create instead of customer_update
     */
    public function add(int $customerId, bool $isNew = false): void
    {
        if ($this->paused || !$customerId) {
            return;
        }
        $activity = $isNew || ($this->pending[$customerId]['activity'] ?? '') === 'customer_create'
            ? 'customer_create' : 'customer_update';
        // Decided now: whether the integration is on can depend on the session (opt-in)
        $this->pending[$customerId] = ['activity' => $activity, 'enabled' => $this->moduleState->isEventEnabled($activity)];
        if (!$this->flushRegistered) {
            register_shutdown_function([$this, 'flush']);
            $this->flushRegistered = true;
        }
    }

    /**
     * Run a save without sending customer events, e.g. when TrueLoyal itself wrote the data
     *
     * @template T
     * @param callable(): T $save
     * @return T
     */
    public function withoutSync(callable $save): mixed
    {
        $this->paused++;
        try {
            return $save();
        } finally {
            $this->paused--;
        }
    }

    /**
     * Send the recorded events (runs at the end of the request)
     */
    public function flush(): void
    {
        $pending = $this->pending;
        $this->pending = [];
        foreach ($pending as $customerId => $event) {
            if (!$event['enabled']) {
                continue;
            }
            try {
                $this->customerRegistry->remove($customerId);
                $customer = $this->customerRepository->getById($customerId);
                $memberId = $this->memberIdResolver->forCustomer($customerId, (string)$customer->getEmail());
                $this->sender->send($event['activity'], $memberId, $customer->__toArray());
            } catch (\Throwable $e) {
                $this->logger->addErrorLog('TrueLoyal: ' . $event['activity'] . ' failed: ' . $e->getMessage());
            }
        }
    }
}
