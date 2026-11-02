<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Model\Event\Customer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\Data\Customer;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Logger\Logger;
use TrueLoyal\LoyaltyRewards\Model\Event\Customer\CustomerSync;
use TrueLoyal\LoyaltyRewards\Model\Event\EventSender;
use TrueLoyal\LoyaltyRewards\Model\MemberIdResolver;
use TrueLoyal\LoyaltyRewards\Model\ModuleState;

class CustomerSyncTest extends TestCase
{
    /** @var array<int, array{0: string, 1: string}> */
    private array $sent = [];

    public function testOneEventPerCustomerAndCreateWins(): void
    {
        $sync = $this->sync(['customer_create', 'customer_update']);
        $new = new DataObject();
        $sync->beforeSave($new);
        $new->setId(7);
        $sync->afterSave($new);
        $sync->add(7);
        $sync->add(7);
        $sync->add(8);
        $sync->add(8);
        $sync->flush();
        $this->assertSame([['customer_create', 'm7'], ['customer_update', 'm8']], $this->sent);
    }

    public function testExistingCustomerIsAnUpdate(): void
    {
        $sync = $this->sync(['customer_update']);
        $existing = new DataObject(['id' => 9]);
        $sync->beforeSave($existing);
        $sync->afterSave($existing);
        $sync->flush();
        $this->assertSame([['customer_update', 'm9']], $this->sent);
    }

    public function testDisabledEventsAndPausedSavesSendNothing(): void
    {
        $sync = $this->sync(['customer_update']);
        $sync->add(1, true);
        $sync->withoutSync(fn () => $sync->add(2));
        $sync->flush();
        $sync->flush();
        $this->assertSame([], $this->sent);
    }

    private function sync(array $enabledEvents): CustomerSync
    {
        $state = $this->createMock(ModuleState::class);
        $state->method('isEventEnabled')->willReturnCallback(fn ($e) => in_array($e, $enabledEvents, true));
        $repository = $this->createMock(CustomerRepositoryInterface::class);
        $repository->method('getById')->willReturnCallback(function ($id) {
            $customer = $this->getMockBuilder(Customer::class)->disableOriginalConstructor()
                ->onlyMethods(['getEmail', '__toArray'])->getMock();
            $customer->method('getEmail')->willReturn("c$id@example.com");
            $customer->method('__toArray')->willReturn(['id' => $id]);
            return $customer;
        });
        $resolver = $this->createMock(MemberIdResolver::class);
        $resolver->method('forCustomer')->willReturnCallback(fn ($id) => "m$id");
        $sender = $this->createMock(EventSender::class);
        $sender->method('send')->willReturnCallback(function ($activity, $memberId) {
            $this->sent[] = [$activity, $memberId];
        });
        return new CustomerSync($state, $repository, $this->createMock(CustomerRegistry::class), $resolver, $sender,
            $this->createMock(Logger::class));
    }
}
