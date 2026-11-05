<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Logger;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Logger\Logger;

class LoggerTest extends TestCase
{
    private const API_KEY = 'unit-test-api-key-0123456789';
    private const SIGNATURE = 'unit-test-signature-abcdef';

    private TestHandler $handler;
    private Logger $logger;

    protected function setUp(): void
    {
        $this->handler = new TestHandler();
        /* Same formatter options as Magento's file handler */
        $this->handler->setFormatter(new LineFormatter(null, null, true));
        $this->logger = new Logger('trueloyalLog', [$this->handler]);
    }

    /**
     * @return string[] formatted log lines, as written to the log file
     */
    private function lines(): array
    {
        return array_map(
            static fn ($record): string => rtrim((string)$record['formatted'], "\n"),
            $this->handler->getRecords()
        );
    }

    private function line(string $prefix): string
    {
        foreach ($this->lines() as $line) {
            if (str_contains($line, 'trueloyalLog.INFO: ' . $prefix)) {
                return $line;
            }
        }
        $this->fail('No "' . $prefix . '" line logged');
    }

    private function assertNotLogged(string $secret): void
    {
        foreach ($this->lines() as $line) {
            $this->assertStringNotContainsString($secret, $line);
        }
    }

    public function testLiveApiKeyIsMaskedAndLineFormatIsKept(): void
    {
        $url = 'https://api.example.com/v2/loyalty/members/m1?idParam=member_id';
        $params = '{"reward_id":"r1","member_id":"m1"}';
        $response = '{"data":{"available_points":120}}';
        $this->logger->loggedAsInfoData($url, 'get', '', [
            'content-type' => 'application/json',
            'api-key' => self::API_KEY,
            'partner-id' => 'partner-1',
        ], $params, $response);

        $this->assertNotLogged(self::API_KEY);
        $headers = $this->line('Headers: ');
        $this->assertStringContainsString('"api-key":"***"', $headers);
        $this->assertStringContainsString('"partner-id":"partner-1"', $headers);
        $this->assertStringContainsString('"content-type":"application\/json"', $headers);

        /* The QA fixtures parse the URL, RequestType and Params lines with these patterns */
        $this->assertSame(1, preg_match('/trueloyalLog\.INFO: URL: (\S+)/', $this->line('URL: '), $m));
        $this->assertSame($url, $m[1]);
        $this->assertSame(1, preg_match('/trueloyalLog\.INFO: RequestType: (\w+)/', $this->line('RequestType: '), $m));
        $this->assertSame('get', $m[1]);
        $this->assertSame(1, preg_match('/trueloyalLog\.INFO: Params: (.*) \[\] \[\]$/', $this->line('Params: '), $m));
        $this->assertSame($params, $m[1]);

        $messages = array_map(static fn ($record): string => (string)$record['message'], $this->handler->getRecords());
        $this->assertSame([
            '==============Start==============',
            'URL: ' . $url,
            'RequestType: get',
            'Message: ',
            'Headers: {"content-type":"application\/json","api-key":"***","partner-id":"partner-1"}',
            'Params: ' . $params,
            'Response: ' . $response,
            '==============End===============',
        ], $messages);
    }

    public function testEventSignatureIsMaskedAndNonceIsKept(): void
    {
        $body = '{"event":"order_create","data":{"id":"1"}}';
        $nonce = round(1700000000000.4);
        $this->logger->loggedAsInfoData('https://hooks.example.com/webhook/abc', 'post', $body . ':' . $nonce, [
            'content-type' => 'application/json',
            'x-magento-signature' => self::SIGNATURE,
            'nonce' => $nonce,
            'partner-id' => 'partner-1',
        ], $body, '{"success":true}');

        $this->assertNotLogged(self::SIGNATURE);
        $this->assertStringContainsString(
            'Headers: {"content-type":"application\/json","x-magento-signature":"***",'
            . '"nonce":' . json_encode($nonce) . ',"partner-id":"partner-1"}',
            $this->line('Headers: ')
        );
        $this->assertStringContainsString('Message: ' . $body . ':' . $nonce . ' ', $this->line('Message: '));
        $this->assertStringContainsString('Params: ' . $body . ' ', $this->line('Params: '));
    }

    public function testIntegrationResponseSecretKeyIsMasked(): void
    {
        $response = json_encode([
            'success' => true,
            'data' => [
                'id' => 'integration-1',
                'integration_type' => 'magento_to_trueloyal',
                'config' => [
                    'events' => ['order_create', 'review_submitted'],
                    'secret_key' => self::API_KEY,
                    'zif_config' => ['workflow_id' => 'wf-1', 'workflow_url' => 'https://hooks.example.com/webhook/abc'],
                ],
            ],
        ], JSON_PRETTY_PRINT);

        $redacted = $this->logger->redactSecrets($response);

        $this->assertStringNotContainsString(self::API_KEY, $redacted);
        $this->assertStringContainsString('"secret_key": "***"', $redacted);
        $this->assertStringContainsString('"workflow_url": "https:\/\/hooks.example.com\/webhook\/abc"', $redacted);
        $this->assertStringContainsString('"id": "integration-1"', $redacted);
        $this->assertStringContainsString('"order_create"', $redacted);
        $this->assertSame(
            str_replace('"secret_key": "' . self::API_KEY . '"', '"secret_key": "***"', $response),
            $redacted
        );
    }

    public static function secretStrings(): array
    {
        return [
            'api-key' => [json_encode(['api-key' => self::API_KEY]), json_encode(['api-key' => '***'])],
            'api_key, mixed case, spaces' => ['{"API_Key" : "' . self::API_KEY . '"}', '{"API_Key" : "***"}'],
            'secret_key with an escaped quote' => [
                '{"secret_key":"a\\"b' . self::API_KEY . '","x":1}',
                '{"secret_key":"***","x":1}',
            ],
            'signature' => [
                json_encode(['x-magento-signature' => self::API_KEY]),
                json_encode(['x-magento-signature' => '***']),
            ],
            'empty value' => ['{"secret_key":""}', '{"secret_key":"***"}'],
            'no secret' => ['{"coupon_code":"SAVE10","url_key":"shoe"}', '{"coupon_code":"SAVE10","url_key":"shoe"}'],
            'invalid UTF-8 is kept' => [
                "{\"name\":\"\xC3\x28\",\"api_key\":\"k\"}",
                "{\"name\":\"\xC3\x28\",\"api_key\":\"***\"}",
            ],
            'not JSON' => ['body:1700000000000', 'body:1700000000000'],
            'empty string' => ['', ''],
        ];
    }

    #[DataProvider('secretStrings')]
    public function testRedactSecrets(string $text, string $expected): void
    {
        $this->assertSame($expected, $this->logger->redactSecrets($text));
    }

    public function testRedactSecretsKeepsNonStrings(): void
    {
        $this->assertNull($this->logger->redactSecrets(null));
        $this->assertSame(['a' => 1], $this->logger->redactSecrets(['a' => 1]));
    }

    public function testHeaderNamesAreMatchedCaseInsensitively(): void
    {
        $this->assertSame(
            ['API-Key' => '***', 'X-Magento-Signature' => '***', 'Partner-ID' => 'p', 0 => 'x'],
            $this->logger->redactHeaders([
                'API-Key' => self::API_KEY,
                'X-Magento-Signature' => self::SIGNATURE,
                'Partner-ID' => 'p',
                0 => 'x',
            ])
        );
    }

    public function testEmptyAndNullValuesDoNotThrow(): void
    {
        $this->logger->loggedAsInfoData('https://api.example.com/v2/loyalty/x', 'get', null, null, '', null);
        $this->logger->loggedAsInfoData('https://api.example.com/v2/loyalty/x', 'get', '', [], null, '');

        $this->assertCount(16, $this->handler->getRecords());
        $this->assertStringContainsString('Headers: []', $this->line('Headers: '));
    }
}
