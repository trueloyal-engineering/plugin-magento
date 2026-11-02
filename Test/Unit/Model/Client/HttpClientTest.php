<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Model\Client;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Logger\Logger;
use TrueLoyal\LoyaltyRewards\Model\Client\HttpClient;

class HttpClientTest extends TestCase
{
    /** @var array<int, \Exception|array{0: int, 1: string}> one entry per attempt */
    private array $attempts;
    private int $sent = 0;
    private ?array $logged = null;

    public function testRetriesConnectionErrorsWhenAllowed(): void
    {
        $this->attempts = [new \Exception('Failed to connect to api.trueloyal.com port 443'), [200, '{"ok":1}']];
        $this->assertSame(['status' => 200, 'body' => '{"ok":1}'], $this->client()->send('get', 'u', [], '', 10, true));
        $this->assertSame(2, $this->sent);
    }

    public function testDoesNotRetryWithoutPermission(): void
    {
        $this->attempts = [new \Exception('Failed to connect'), [200, 'x']];
        $this->expectExceptionMessage('Failed to connect');
        try {
            $this->client()->send('post', 'u', [], 'b', 10, false);
        } finally {
            $this->assertSame(1, $this->sent);
        }
    }

    public function testDoesNotRetryTimeoutsAfterTheRequestWasSent(): void
    {
        $this->attempts = [new \Exception('Operation timed out after 10001 milliseconds'), [200, 'x']];
        $this->expectExceptionMessage('Operation timed out');
        $this->client()->send('post', 'u', [], 'b', 10, true);
    }

    public function testRetriesGatewayErrorsAtMostTwice(): void
    {
        $this->attempts = [[502, ''], [503, ''], [504, 'still down'], [200, 'never sent']];
        $this->assertSame(['status' => 504, 'body' => 'still down'], $this->client()->send('get', 'u', [], '', 10, true));
        $this->assertSame(3, $this->sent);
    }

    public function testLogMasksCredentials(): void
    {
        $this->attempts = [[200, 'ok']];
        $headers = ['api-key' => 'secret', 'x-magento-signature' => 'sig', 'partner-id' => 'p'];
        $this->client()->send('post', 'u', $headers, '{"config":{"secret_key":"secret","events":[]}}');
        $this->assertSame(['api-key' => '***', 'x-magento-signature' => '***', 'partner-id' => 'p'], $this->logged[3]);
        $this->assertSame('{"config":{"secret_key":"***","events":[]}}', $this->logged[4]);
    }

    private function client(): HttpClient
    {
        $factory = $this->createMock(CurlFactory::class);
        $factory->method('create')->willReturnCallback(function () {
            $attempt = $this->attempts[$this->sent++];
            $curl = $this->createMock(Curl::class);
            $send = $attempt instanceof \Exception ? $this->throwException($attempt) : $this->returnValue(null);
            $curl->method('get')->will($send);
            $curl->method('post')->will($send);
            if (is_array($attempt)) {
                $curl->method('getStatus')->willReturn($attempt[0]);
                $curl->method('getBody')->willReturn($attempt[1]);
            }
            return $curl;
        });
        $logger = $this->createMock(Logger::class);
        $logger->method('loggedAsInfoData')->willReturnCallback(function (...$args) {
            $this->logged = $args;
        });
        return new class ($factory, $logger) extends HttpClient {
        };
    }
}
