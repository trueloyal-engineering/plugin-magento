<?php

namespace TrueLoyal\LoyaltyRewards\Framework\HTTP\Client;

use Magento\Framework\HTTP\Client\Curl as MainCurl;

class Curl extends MainCurl
{
    /**
     * Parse headers - CURL callback function : We have override due to Compatibility of version M230.
     *
     * @param resource $ch curl handle, not needed
     * @param string $data
     * @return int
     * @throws \Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function parseHeaders($ch, $data)
    {
        $data = $data !== null ? $data : '';
        if ($this->_headerCount == 0) {
            $line = explode(" ", trim($data), 3);
            if (count($line) < 2) {
                $this->doError("Invalid response line returned from server: " . $data);
            }
            $this->_responseStatus = (int)$line[1];
        } else {
            $name = $value = '';
            $out = explode(": ", trim($data), 2);
            if (count($out) == 2) {
                $name = $out[0];
                $value = $out[1];
            }

            if (strlen($name)) {
                if ('set-cookie' === strtolower($name)) {
                    $this->_responseHeaders['Set-Cookie'][] = $value;
                } else {
                    $this->_responseHeaders[$name] = $value;
                }
            }
        }
        $this->_headerCount++;

        return strlen($data);
    }
}
