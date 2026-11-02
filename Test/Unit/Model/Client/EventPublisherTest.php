<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Model\Client;

use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Model\Client\EventPublisher;
use TrueLoyal\LoyaltyRewards\Model\Client\HttpClient;
use TrueLoyal\LoyaltyRewards\Model\Client\ResponseParser;
use TrueLoyal\LoyaltyRewards\Model\Settings;

class EventPublisherTest extends TestCase
{
    public function testSignsBodyAndNonceWithTheApiKey(): void
    {
        $settings = $this->createMock(Settings::class);
        $settings->method('getApiKey')->willReturn('k');
        $settings->method('getPartnerId')->willReturn('p');
        $http = $this->createMock(HttpClient::class);
        $http->expects($this->once())->method('send')->willReturnCallback(
            function (string $method, string $url, array $headers, string $body, int $timeout, bool $retry, string $msg) {
                $this->assertSame(['post', 'https://hook', '{"a":1}', true], [$method, $url, $body, $retry]);
                $this->assertMatchesRegularExpression('/^\d{13}$/', $headers['nonce']);
                $this->assertSame('{"a":1}:' . $headers['nonce'], $msg);
                $this->assertSame(hash_hmac('sha512', $msg, 'k'), $headers['x-magento-signature']);
                $this->assertSame('p', $headers['partner-id']);
                return ['status' => 200, 'body' => '{"message":"Workflow was started"}'];
            }
        );
        $result = (new EventPublisher($settings, $http, new ResponseParser()))->send('https://hook', '{"a":1}');
        $this->assertTrue($result['success']);
    }
}
