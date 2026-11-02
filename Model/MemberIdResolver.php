<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\SessionFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * The member_id a customer has in TrueLoyal
 *
 * member_email mode (default): prefix + email. member_id mode: prefix + customer ID, zero-padded so the whole value
 * is at least 3 characters; guests fall back to prefix + email.
 */
class MemberIdResolver
{
    private const MIN_LENGTH = 3;

    public function __construct(
        private readonly Settings $settings,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly SessionFactory $customerSessionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Member ID of a customer (or a guest, by email)
     *
     * Without an email in member_email mode, the customer's email is looked up.
     */
    public function forCustomer(
        int|string|null $customerId,
        ?string $email = null,
        int|string|null $storeId = null
    ): string {
        $prefix = $this->settings->getMemberIdentifierPrefix($storeId);
        if ($this->settings->getMemberIdentifierType($storeId) === 'member_id') {
            if ($customerId) {
                return $this->formatId($prefix, (string)$customerId);
            }
            return $email ? $prefix . $email : '';
        }
        if ($email !== null) {
            return $email ? $prefix . $email : '';
        }
        $email = $customerId ? $this->getCustomerEmail($customerId) : '';
        return $email ? $prefix . $email : '';
    }

    /**
     * Member ID of the logged-in customer, '' for guests
     */
    public function forSession(): string
    {
        $session = $this->customerSessionFactory->create();
        $prefix = $this->settings->getMemberIdentifierPrefix();
        if ($this->settings->getMemberIdentifierType() === 'member_id') {
            $customerId = $session->getCustomerId();
            return $customerId ? $this->formatId($prefix, (string)$customerId) : '';
        }
        $email = (string)$session->getCustomer()->getEmail();
        return $email ? $prefix . $email : '';
    }

    private function formatId(string $prefix, string $customerId): string
    {
        if (strlen($prefix) + strlen($customerId) < self::MIN_LENGTH) {
            return $prefix . str_pad($customerId, self::MIN_LENGTH - strlen($prefix), '0', STR_PAD_LEFT);
        }
        return $prefix . $customerId;
    }

    private function getCustomerEmail(int|string $customerId): string
    {
        try {
            return (string)$this->customerRepository->getById((int)$customerId)->getEmail();
        } catch (LocalizedException $e) {
            $this->logger->error($e->getMessage());
            return '';
        }
    }
}
