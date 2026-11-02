<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\Client;

/**
 * TrueLoyal answers errors with HTTP 200 and {"success": false, "error_code", "reason"}: read the body, not the status
 */
class ResponseParser
{
    /**
     * @return array{success: bool, result: mixed} result is the decoded body, or the error reason
     */
    public function parse(string $body): array
    {
        $response = $body !== '' ? json_decode($body, true) : null;
        if (empty($response) || !is_array($response)) {
            return ['success' => false, 'result' => __('Something went wrong, check and try again')];
        }
        if (isset($response['error_code']) || isset($response['error'])) {
            return ['success' => false, 'result' => $response['reason'] ?? null];
        }
        return ['success' => true, 'result' => $response];
    }
}
