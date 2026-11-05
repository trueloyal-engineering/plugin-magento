<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Helper;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Helper\Config;
use TrueLoyal\LoyaltyRewards\Helper\Data;
use TrueLoyal\LoyaltyRewards\Logger\Logger;

class ConfigTest extends TestCase
{
    private const API_KEY = 'unit-test-api-key-0123456789';
    private const PARTNER_ID = 'partner-1';

    private TestHandler $handler;
    private Curl&MockObject $curl;
    private Data&MockObject $helper;
    private array $sentHeaders = [];

    protected function setUp(): void
    {
        $this->handler = new TestHandler();
        $this->curl = $this->createMock(Curl::class);
        $this->curl->method('setHeaders')->willReturnCallback(function (array $headers): void {
            $this->sentHeaders = $headers;
        });
        $this->helper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getApiKey', 'getPartnerId', 'getRewardEvents', 'saveWebHookUrl', 'saveWebHookIntegrationID'])
            ->getMock();
        $this->helper->method('getApiKey')->willReturn(self::API_KEY);
        $this->helper->method('getPartnerId')->willReturn(self::PARTNER_ID);
        $this->helper->method('getRewardEvents')->willReturn(['order_create']);
        $this->helper->logger = new Logger('trueloyalLog', [$this->handler]);
        $this->helper->json = new Json();
        (new \ReflectionProperty(Config::class, 'curl'))->setValue($this->helper, $this->curl);
    }

    private function assertNotLogged(string $secret): void
    {
        $this->assertNotEmpty($this->handler->getRecords());
        foreach ($this->handler->getRecords() as $record) {
            $this->assertStringNotContainsString($secret, (string)$record['message']);
        }
    }

    public function testLiveCallSendsTheApiKeyButDoesNotLogIt(): void
    {
        $url = 'https://api.example.com/v2/loyalty/members/m1?idParam=member_id';
        $this->curl->expects($this->once())->method('get')->with($url);
        $this->curl->method('getBody')->willReturn('{"data":{"available_points":120}}');

        $result = $this->helper->request($url, '', 'get', 'live_api');

        $this->assertTrue($result['success']);
        $this->assertSame([
            'content-type' => 'application/json',
            'api-key' => self::API_KEY,
            'partner-id' => self::PARTNER_ID,
        ], $this->sentHeaders);
        $this->assertNotLogged(self::API_KEY);
        $this->assertTrue($this->handler->hasInfoThatContains('"api-key":"***"'));
        $this->assertTrue($this->handler->hasInfoThatContains('"partner-id":"' . self::PARTNER_ID . '"'));
    }

    public function testEventCallSendsTheSignatureButDoesNotLogIt(): void
    {
        $url = 'https://hooks.example.com/webhook/abc';
        $body = '{"event":"order_create","data":{"id":"1"}}';
        $this->curl->expects($this->once())->method('post')->with($url, $body);
        $this->curl->method('getBody')->willReturn('{"success":true}');

        $this->helper->request($url, $body, 'post', 'event_api');

        $signature = $this->sentHeaders['x-magento-signature'];
        $this->assertSame(hash_hmac('sha512', $body . ':' . $this->sentHeaders['nonce'], self::API_KEY), $signature);
        $this->assertSame(self::PARTNER_ID, $this->sentHeaders['partner-id']);
        $this->assertNotLogged($signature);
        $this->assertNotLogged(self::API_KEY);
        $this->assertTrue($this->handler->hasInfoThatContains('"x-magento-signature":"***"'));
        $this->assertTrue($this->handler->hasInfoThatContains('Params: ' . $body));
    }

    public function testIntegrationSendsTheSecretKeyButDoesNotLogIt(): void
    {
        $url = 'https://api.example.com/v2/loyalty/integrations';
        $posted = null;
        $this->curl->expects($this->once())->method('post')->with($url)
            ->willReturnCallback(function (string $uri, string $body) use (&$posted): void {
                $posted = $body;
            });
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn(json_encode([
            'success' => true,
            'data' => [
                'id' => 'integration-1',
                'config' => [
                    'events' => ['order_create'],
                    'secret_key' => self::API_KEY,
                    'zif_config' => ['workflow_url' => 'https://hooks.example.com/webhook/abc'],
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $integration = $this->helper->createOrUpdateZIFIntegration($url);

        $this->assertSame('integration-1', $integration['id']);
        $this->assertSame(self::API_KEY, $this->sentHeaders['api-key']);
        $this->assertSame(self::API_KEY, json_decode($posted, true)['config']['secret_key']);
        $this->assertNotLogged(self::API_KEY);
        $this->assertTrue($this->handler->hasInfoThatContains('"api-key":"***"'));
        $this->assertTrue($this->handler->hasInfoThatContains('"secret_key": "***"'));
        $this->assertTrue($this->handler->hasInfoThatContains('https:\/\/hooks.example.com\/webhook\/abc'));
    }
}
