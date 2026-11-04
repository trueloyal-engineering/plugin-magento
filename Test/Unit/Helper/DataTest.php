<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class DataTest extends TestCase
{
    public static function credentials(): array
    {
        return [
            'matching headers' => ['key', 'partner', 'key', 'partner', true],
            'wrong key' => ['key', 'partner', 'wrong', 'partner', false],
            'wrong partner ID' => ['key', 'partner', 'key', 'wrong', false],
            'missing headers' => ['key', 'partner', false, false, false],
            'empty headers' => ['key', 'partner', '', '', false],
            'nothing configured, no headers' => ['', '', false, false, false],
            'nothing configured, empty headers' => ['', '', '', '', false],
        ];
    }

    #[DataProvider('credentials')]
    public function testIsValidateApiAuth(string $key, string $partner, mixed $headerKey, mixed $headerPartner, bool $valid): void
    {
        $helper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getApiKey', 'getPartnerId'])
            ->getMock();
        $helper->method('getApiKey')->willReturn($key);
        $helper->method('getPartnerId')->willReturn($partner);

        $this->assertSame($valid, $helper->isValidateApiAuth($headerKey, $headerPartner));
    }
}
