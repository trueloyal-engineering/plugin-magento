<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\Client;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use TrueLoyal\LoyaltyRewards\Logger\Logger;

/**
 * HTTP transport for every call to TrueLoyal: timeouts, retries of failed connections, and the request log
 * (var/log/trueloyal.log, credentials masked)
 */
class HttpClient
{
    public const CONNECT_TIMEOUT = 5;
    public const TIMEOUT = 10;
    private const RETRY_DELAYS_MS = [200, 500];
    /** Errors where the request never reached TrueLoyal, so sending it again can't duplicate it */
    private const CONNECTION_ERRORS = ['Could not resolve host', 'Failed to connect', 'Connection refused',
        'Connection timed out', 'Resolving timed out', 'SSL connect error'];
    private const MASKED_HEADERS = ['api-key', 'x-magento-signature'];

    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Logger $logger
    ) {
    }

    /**
     * Send a request and return the raw response
     *
     * @param string $method 'get' or 'post'
     * @param array<string, string|int|float> $headers
     * @param bool $retry Retry when the connection fails or TrueLoyal's gateway answers 502/503/504. Only for
     *     calls that are safe to repeat.
     * @param string $logMessage Extra "Message:" line for the log (the signed string of webhook calls)
     * @return array{status: int, body: string}
     * @throws \Exception when the request can't be sent
     */
    public function send(
        string $method,
        string $url,
        array $headers,
        string $body = '',
        int $timeout = self::TIMEOUT,
        bool $retry = false,
        string $logMessage = ''
    ): array {
        $attempt = 0;
        while (true) {
            try {
                $curl = $this->curlFactory->create();
                $curl->setOption(CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
                $curl->setTimeout($timeout);
                $curl->setHeaders($headers);
                $method === 'post' ? $curl->post($url, $body) : $curl->get($url);
                $response = ['status' => $curl->getStatus(), 'body' => (string)$curl->getBody()];
            } catch (\Exception $e) {
                if ($retry && $this->isConnectionError($e) && isset(self::RETRY_DELAYS_MS[$attempt])) {
                    $this->logger->warning(sprintf('Retrying %s after: %s', $url, $e->getMessage()));
                    usleep(self::RETRY_DELAYS_MS[$attempt++] * 1000);
                    continue;
                }
                throw $e;
            }
            $gatewayError = in_array($response['status'], [502, 503, 504], true);
            if ($retry && $gatewayError && isset(self::RETRY_DELAYS_MS[$attempt])) {
                $this->logger->warning(sprintf('Retrying %s after HTTP %d', $url, $response['status']));
                usleep(self::RETRY_DELAYS_MS[$attempt++] * 1000);
                continue;
            }
            $this->logger->loggedAsInfoData(
                $url,
                $method,
                $logMessage,
                $this->mask($headers),
                preg_replace('/("secret_key":\s*")[^"]*/', '$1***', $body),
                $response['body']
            );
            return $response;
        }
    }

    private function isConnectionError(\Exception $e): bool
    {
        foreach (self::CONNECTION_ERRORS as $error) {
            if (stripos($e->getMessage(), $error) !== false) {
                return true;
            }
        }
        return false;
    }

    private function mask(array $headers): array
    {
        foreach (self::MASKED_HEADERS as $name) {
            if (isset($headers[$name]) && $headers[$name] !== '') {
                $headers[$name] = '***';
            }
        }
        return $headers;
    }
}
