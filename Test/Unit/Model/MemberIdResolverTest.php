<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\SessionFactory;
use Magento\Framework\DataObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TrueLoyal\LoyaltyRewards\Model\MemberIdResolver;
use TrueLoyal\LoyaltyRewards\Model\Settings;

class MemberIdResolverTest extends TestCase
{
    public static function customers(): array
    {
        return [
            'email mode, email given' => ['member_email', '', 4, 'a@example.com', 'a@example.com'],
            'email mode, prefix' => ['member_email', 'tl-', 4, 'a@example.com', 'tl-a@example.com'],
            'email mode, email looked up' => ['member_email', '', 4, null, 'looked-up@example.com'],
            'email mode, guest without email' => ['member_email', '', null, '', ''],
            'id mode, padded to 3' => ['member_id', '', 4, 'a@example.com', '004'],
            'id mode, prefix counts towards 3' => ['member_id', 'x', 4, null, 'x04'],
            'id mode, long prefix' => ['member_id', 'shop-', 4, null, 'shop-4'],
            'id mode, long ID' => ['member_id', '', 12345, null, '12345'],
            'id mode, guest falls back to email' => ['member_id', 'p', null, 'g@example.com', 'pg@example.com'],
        ];
    }

    #[DataProvider('customers')]
    public function testForCustomer(string $type, string $prefix, ?int $id, ?string $email, string $expected): void
    {
        $this->assertSame($expected, $this->resolver($type, $prefix)->forCustomer($id, $email));
    }

    public function testForSessionInIdMode(): void
    {
        $this->assertSame('007', $this->resolver('member_id', '', 7)->forSession());
        $this->assertSame('', $this->resolver('member_id', '', null)->forSession());
    }

    public function testForSessionInEmailMode(): void
    {
        $this->assertSame('tl-s@example.com', $this->resolver('member_email', 'tl-', 7, 's@example.com')->forSession());
        $this->assertSame('', $this->resolver('member_email', 'tl-', null, null)->forSession());
    }

    private function resolver(string $type, string $prefix, ?int $sessionId = null, ?string $sessionEmail = null): MemberIdResolver
    {
        $settings = $this->createMock(Settings::class);
        $settings->method('getMemberIdentifierType')->willReturn($type);
        $settings->method('getMemberIdentifierPrefix')->willReturn($prefix);
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('looked-up@example.com');
        $repository = $this->createMock(CustomerRepositoryInterface::class);
        $repository->method('getById')->willReturn($customer);
        $sessionCustomer = new DataObject(['email' => $sessionEmail]);
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()
            ->onlyMethods(['getCustomerId', 'getCustomer'])->getMock();
        $session->method('getCustomerId')->willReturn($sessionId);
        $session->method('getCustomer')->willReturn($sessionCustomer);
        $sessionFactory = $this->createMock(SessionFactory::class);
        $sessionFactory->method('create')->willReturn($session);
        return new MemberIdResolver($settings, $repository, $sessionFactory, $this->createMock(LoggerInterface::class));
    }
}
