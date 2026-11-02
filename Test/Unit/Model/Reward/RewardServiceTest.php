<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Model\Reward;

use Magento\Customer\Model\Session;
use Magento\Framework\App\State;
use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Logger\Logger;
use TrueLoyal\LoyaltyRewards\Model\Client\LiveApi;
use TrueLoyal\LoyaltyRewards\Model\Reward\RewardService;

class RewardServiceTest extends TestCase
{
    private array $calls = [];
    private array $sessionData = [];

    public function testGetsAreCachedUntilARedemption(): void
    {
        $service = $this->service('frontend');
        $service->getMember('m');
        $service->getMember('m');
        $this->assertCount(1, $this->calls);

        // a new request in the same session still uses the cache
        $this->service('frontend')->getMember('m');
        $this->assertCount(1, $this->calls);

        $service->redeem('{}');
        $service->getMember('m');
        $this->assertSame(['get members/m', 'post transactions/redeem', 'get members/m'], $this->calls);
    }

    public function testWithoutAStorefrontSessionOnlyThisRequestIsCached(): void
    {
        $this->service('crontab')->getRewards('m');
        $this->service('crontab')->getRewards('m');
        $this->assertCount(2, $this->calls);
        $this->assertSame([], $this->sessionData);
    }

    private function service(string $area): RewardService
    {
        $api = $this->createMock(LiveApi::class);
        $api->method('memberUrl')->willReturnCallback(fn ($m, $suffix = '') => "members/$m$suffix");
        $api->method('transactionUrl')->willReturnCallback(fn ($s) => "transactions/$s");
        $api->method('request')->willReturnCallback(function ($method, $url) {
            $this->calls[] = "$method $url";
            return ['success' => true, 'result' => []];
        });
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()
            ->onlyMethods(['getData'])->addMethods(['setData', 'unsetData'])->getMock();
        $session->method('getData')->willReturnCallback(fn ($k) => $this->sessionData[$k] ?? null);
        $session->method('setData')->willReturnCallback(function ($k, $v) use ($session) {
            $this->sessionData[$k] = $v;
            return $session;
        });
        $session->method('unsetData')->willReturnCallback(function ($k) use ($session) {
            unset($this->sessionData[$k]);
            return $session;
        });
        $state = $this->createMock(State::class);
        $state->method('getAreaCode')->willReturn($area);
        return new RewardService($api, $session, $state, $this->createMock(Logger::class));
    }
}
