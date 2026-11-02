<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Model\Client;

use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Model\Client\ResponseParser;

class ResponseParserTest extends TestCase
{
    public function testSuccess(): void
    {
        $this->assertSame(['success' => true, 'result' => ['data' => ['x' => 1]]], (new ResponseParser())->parse('{"data":{"x":1}}'));
    }

    public function testErrorsComeBackWithHttp200(): void
    {
        $body = '{"success": false, "error_code": "INVALID_MEMBER", "reason": "Member not found"}';
        $this->assertSame(['success' => false, 'result' => 'Member not found'], (new ResponseParser())->parse($body));
    }

    public function testEmptyOrInvalidBody(): void
    {
        $this->assertFalse((new ResponseParser())->parse('')['success']);
        $this->assertFalse((new ResponseParser())->parse('<html>')['success']);
    }
}
