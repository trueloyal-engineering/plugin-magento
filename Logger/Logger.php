<?php

namespace TrueLoyal\LoyaltyRewards\Logger;

use Monolog\Logger as MonologLogger;

class Logger extends MonologLogger
{

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
        $this->info("Message: " . $message);
        $this->info("Headers: " . json_encode($headers));
        $this->info("Params: " . $params);
        $this->info("Response: " . $response);
        $this->info("==============End===============");
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
