<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\SessionFactory;
use Magento\Framework\Api\AttributeValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Model\ModuleState;
use TrueLoyal\LoyaltyRewards\Model\Settings;

class ModuleStateTest extends TestCase
{
    public static function states(): array
    {
        return [
            'off' => [false, true, null, null, []],
            'on, auto-enrollment' => [true, true, 5, null, ['order_create', 'order_refund']],
            'on, guest, no auto-enrollment' => [true, false, null, null, ['order_create', 'order_refund']],
            'on, opted-in customer' => [true, false, 5, '1', ['order_create', 'order_refund']],
            'on, customer not opted in' => [true, false, 5, null, []],
        ];
    }

    #[DataProvider('states')]
    public function testRewardEvents(bool $active, bool $autoEnroll, ?int $customerId, ?string $optIn, array $events): void
    {
        $settings = $this->createMock(Settings::class);
        $settings->method('isActive')->willReturn($active);
        $settings->method('isAutoEnrollmentEnabled')->willReturn($autoEnroll);
        $settings->method('getOptInAttributeCode')->willReturn('loyalty_opt_in');
        $settings->method('getRewardEvents')->willReturn(['order_create', 'order_refund']);
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()
            ->onlyMethods(['getCustomerId'])->getMock();
        $session->method('getCustomerId')->willReturn($customerId);
        $sessionFactory = $this->createMock(SessionFactory::class);
        $sessionFactory->method('create')->willReturn($session);
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getCustomAttribute')
            ->willReturn($optIn === null ? null : (new AttributeValue())->setValue($optIn));
        $repository = $this->createMock(CustomerRepositoryInterface::class);
        $repository->method('getById')->willReturn($customer);

        $state = new ModuleState($settings, $sessionFactory, $repository);
        $this->assertSame($events, $state->getRewardEvents());
        $this->assertSame($events !== [], $state->isEventEnabled('order_create'));
        $this->assertFalse($state->isEventEnabled('order_cancelled'));
    }
}
