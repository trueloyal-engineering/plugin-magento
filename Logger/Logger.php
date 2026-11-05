<?php

namespace TrueLoyal\LoyaltyRewards\Logger;

use Monolog\Logger as MonologLogger;

class Logger extends MonologLogger
{
    /**
     * Header names whose values are credentials or signing material (compared in lower case)
     */
    private const SECRET_HEADERS = ['api-key', 'x-magento-signature'];

    /**
     * JSON values of these keys are masked in logged messages, bodies and responses
     */
    private const SECRET_JSON_PATTERN =
        '/("(?:api-key|api_key|secret_key|x-magento-signature)"\s*:\s*")(?:[^"\\\\]|\\\\.)*+"/i';

    /**
     * Replacement for a masked value
     */
    private const MASK = '***';

    /**
     * Logged As Info Data
     *
     * @param mixed $url
     * @param mixed $requestType
     * @param mixed $message
     * @param mixed $headers
     * @param mixed $params
     * @param mixed $response
     */
    public function loggedAsInfoData($url, $requestType, $message, $headers, $params, $response)
    {
        $this->info("==============Start==============");
        $this->info("URL: " . $url);
        $this->info("RequestType: " . $requestType);
        $this->info("Message: " . $this->redactSecrets($message));
        $this->info("Headers: " . json_encode($this->redactHeaders((array)$headers)));
        $this->info("Params: " . $this->redactSecrets($params));
        $this->info("Response: " . $this->redactSecrets($response));
        $this->info("==============End===============");
    }

    /**
     * Mask the API key and the request signature in a header list, keeping the other headers as they are
     *
     * @param array $headers
     * @return array
     */
    public function redactHeaders(array $headers): array
    {
        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string)$name), self::SECRET_HEADERS, true)) {
                $headers[$name] = self::MASK;
            }
        }
        return $headers;
    }

    /**
     * Mask the JSON values of secret keys (API key, secret key, signature) in a logged string
     *
     * @param mixed $text
     * @return mixed the masked string, or the value unchanged when it isn't a string
     */
    public function redactSecrets($text)
    {
        if (!is_string($text)) {
            return $text;
        }
        $redacted = preg_replace(self::SECRET_JSON_PATTERN, '$1' . self::MASK . '"', $text);
        return $redacted ?? '[not logged: redaction failed]';
    }

    /**
     * Add log when getting error on get-set Data
     *
     * @param mixed $logData
     */
    public function addErrorLog($logData)
    {
        $this->error($logData);
    }
}
