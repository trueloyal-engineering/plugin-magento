<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\Client;

use TrueLoyal\LoyaltyRewards\Model\Settings;

/**
 * Sends activity events ({member_id, activity_id, data}) to the merchant's TrueLoyal workflow (web_hook_url)
 *
 * Each request is signed: x-magento-signature = HMAC-SHA512 of "<body>:<nonce>" with the API key, where the nonce
 * is the send time in milliseconds (the workflow rejects old nonces, so sign right before sending).
 */
class EventPublisher
{
    public function __construct(
        private readonly Settings $settings,
        private readonly HttpClient $http,
        private readonly ResponseParser $parser
    ) {
    }

    /**
     * @param string $url the webhook URL (normally Settings::getWebhookUrl())
     * @param string $body the JSON event
     * @param int|string|null $storeId store whose credentials sign the event, default the current
     * @return array{success: bool, result: mixed} the workflow answers before it processes the event, so this only
     *     says the event was delivered
     * @throws \Exception when the request can't be sent
     */
    public function send(string $url, string $body, int|string|null $storeId = null): array
    {
        $nonce = (string)round(microtime(true) * 1000);
        $message = $body . ':' . $nonce;
        $headers = [
            'content-type' => 'application/json',
            'x-magento-signature' => hash_hmac('sha512', $message, $this->settings->getApiKey($storeId)),
            'nonce' => $nonce,
            'partner-id' => $this->settings->getPartnerId($storeId),
        ];
        $response = $this->http->send('post', $url, $headers, $body, HttpClient::TIMEOUT, true, $message);
        return $this->parser->parse($response['body']);
    }
}
