<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\Client;

use TrueLoyal\LoyaltyRewards\Model\Settings;

/**
 * TrueLoyal's live API (members, rewards, transactions), authenticated with the API key
 */
class LiveApi
{
    public function __construct(
        private readonly Settings $settings,
        private readonly HttpClient $http,
        private readonly ResponseParser $parser
    ) {
    }

    /**
     * Call an endpoint by full URL
     *
     * Redeeming is never retried: a repeated redeem would put the points on hold twice.
     *
     * @return array{success: bool, result: mixed}
     * @throws \Exception when the request can't be sent
     */
    public function request(string $method, string $url, string $body = '', int|string|null $storeId = null): array
    {
        $retry = $method === 'get' || !str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '/redeem');
        $response = $this->http->send($method, $url, $this->headers($storeId), $body, HttpClient::TIMEOUT, $retry);
        return $this->parser->parse($response['body']);
    }

    /**
     * URL of an endpoint for a member, e.g. member('tl@example.com', '/rewards')
     */
    public function memberUrl(string $memberId, string $suffix = '', int|string|null $storeId = null): string
    {
        return $this->settings->getLiveApiUrl($storeId) . 'members/' . $memberId . $suffix . '?idParam=member_id';
    }

    public function transactionUrl(string $suffix, int|string|null $storeId = null): string
    {
        return $this->settings->getLiveApiUrl($storeId) . 'transactions/' . $suffix;
    }

    /**
     * @return array<string, string>
     */
    private function headers(int|string|null $storeId): array
    {
        return [
            'content-type' => 'application/json',
            'api-key' => $this->settings->getApiKey($storeId),
            'partner-id' => $this->settings->getPartnerId($storeId),
        ];
    }
}
