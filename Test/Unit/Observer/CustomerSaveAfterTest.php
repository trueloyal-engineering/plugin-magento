<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Observer;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Data\Customer as CustomerData;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Helper\Config;
use TrueLoyal\LoyaltyRewards\Helper\Data;
use TrueLoyal\LoyaltyRewards\Observer\CustomerSaveAfter;
use TrueLoyal\LoyaltyRewards\Test\Unit\StoreScopeTrait;

class CustomerSaveAfterTest extends TestCase
{
    use StoreScopeTrait;

    public static function websiteEvents(): array
    {
        return [
            'customer_create disabled on the customer\'s website' => ['customer_update', false],
            'customer_create enabled on the customer\'s website' => ['customer_create,customer_update', true],
        ];
    }

    /**
     * A customer of website 2 created in the admin (store 0), where Default Config enables every event
     */
    #[DataProvider('websiteEvents')]
    public function testCustomerCreatedInTheAdminUsesTheCustomerWebsiteSettings(string $website2Events, bool $sent): void
    {
        $common = [Config::XML_PATH_LOYALTY_REWARDS_ACTIVE => '1', Config::XML_PATH_AUTO_ENROLLMENT => '1'];
        $helper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request'])
            ->getMock();
        $helper->json = new Json();
        $this->setStoreScope($helper, [
            0 => $common + [
                Config::XML_PATH_REWARD_EVENTS => 'customer_create,customer_update',
                Config::XML_PATH_MEMBER_IDENTIFIER => 'member_email',
                Config::XML_PATH_WEB_HOOK_URL => 'https://hooks.example.test/default',
            ],
            2 => $common + [
                Config::XML_PATH_REWARD_EVENTS => $website2Events,
                Config::XML_PATH_MEMBER_IDENTIFIER => 'member_id',
                Config::XML_PATH_MEMBER_IDENTIFIER_PREFIX => 'abc_',
                Config::XML_PATH_WEB_HOOK_URL => 'https://hooks.example.test/website2',
            ],
        ], 0);
        $posted = null;
        $helper->expects($sent ? $this->once() : $this->never())->method('request')
            ->with('https://hooks.example.test/website2')
            ->willReturnCallback(function (string $url, string $params) use (&$posted): array {
                $posted = $params;
                return ['success' => true, 'result' => []];
            });

        $customer = $this->getMockBuilder(Customer::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $customer->setData(['entity_id' => '37', 'email' => 'member@example.com', 'website_id' => '2', 'store_id' => '2']);
        $savedCustomer = $this->getMockBuilder(CustomerData::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__toArray', 'getEmail'])
            ->getMock();
        $savedCustomer->method('__toArray')->willReturn(['id' => '37', 'email' => 'member@example.com']);
        $savedCustomer->method('getEmail')->willReturn('member@example.com');
        $customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepository->method('getById')->with('37')->willReturn($savedCustomer);

        $observer = new CustomerSaveAfter(
            $helper,
            $this->createMock(Http::class),
            new Registry(),
            $customerRepository,
            $this->createMock(AddressRepositoryInterface::class)
        );
        $observer->execute(new Observer([
            'event' => new Event(['name' => 'customer_save_commit_after', 'customer' => $customer]),
            'customer' => $customer,
        ]));

        if ($sent) {
            $body = json_decode((string)$posted, true);
            $this->assertSame('abc_37', $body['member_id']);
            $this->assertSame('customer_create', $body['activity_id']);
        }
    }
}
