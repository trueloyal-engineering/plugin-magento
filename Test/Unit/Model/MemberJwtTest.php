<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Model;

use Magento\Framework\DataObject;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Helper\Config;
use TrueLoyal\LoyaltyRewards\Model\MemberJwt;

class MemberJwtTest extends TestCase
{
    private const ALL_FIELDS = [
        'store_id' => 'store_id',
        'store_code' => 'store_code',
        'store_currency' => 'store_currency',
        'store_view_code' => 'store_view_code',
        'store_locale' => 'store_locale',
    ];

    /**
     * Custom attributes the JWT sends for the given admin fields (attribute names) and store view
     *
     * @param array $fields attribute names by field ID
     * @param array $store store view: view code, locale and currency, store group id and code
     * @return array
     */
    private function customAttributes(array $fields, array $store): array
    {
        $config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getCustomMemberStoreId',
                'getCustomMemberStoreCode',
                'getCustomMemberStoreCurrency',
                'getCustomMemberStoreViewCode',
                'getCustomMemberStoreLocale',
                'getStoreCurrencyCode',
                'getStoreLocale',
            ])
            ->getMock();
        $config->method('getCustomMemberStoreId')->willReturn($fields['store_id'] ?? '');
        $config->method('getCustomMemberStoreCode')->willReturn($fields['store_code'] ?? '');
        $config->method('getCustomMemberStoreCurrency')->willReturn($fields['store_currency'] ?? '');
        $config->method('getCustomMemberStoreViewCode')->willReturn($fields['store_view_code'] ?? '');
        $config->method('getCustomMemberStoreLocale')->willReturn($fields['store_locale'] ?? '');
        $config->method('getStoreCurrencyCode')->willReturn($store['currency']);
        $config->method('getStoreLocale')->willReturn($store['locale']);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn(new DataObject(['code' => $store['view_code']]));
        $storeManager->method('getGroup')->willReturn(
            new DataObject(['id' => $store['group_id'], 'code' => $store['group_code']])
        );

        $memberJwt = (new \ReflectionClass(MemberJwt::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(MemberJwt::class, 'config'))->setValue($memberJwt, $config);
        (new \ReflectionProperty(MemberJwt::class, 'storeManager'))->setValue($memberJwt, $storeManager);
        return (new \ReflectionMethod(MemberJwt::class, 'getCustomAttributes'))->invoke($memberJwt);
    }

    private static function store(string $viewCode, string $locale): array
    {
        return [
            'view_code' => $viewCode,
            'locale' => $locale,
            'currency' => 'EUR',
            'group_id' => '2',
            'group_code' => 'main',
        ];
    }

    public function testAllFieldsSendTheStoreGroupCurrencyViewCodeAndLocale(): void
    {
        $this->assertSame([
            'store_id' => '2',
            'store_code' => 'main',
            'store_currency' => 'EUR',
            'store_view_code' => 'main_en',
            'store_locale' => 'en_US',
        ], $this->customAttributes(self::ALL_FIELDS, self::store('main_en', 'en_US')));
    }

    public function testWithoutTheNewFieldsTheAttributesAreUnchanged(): void
    {
        $fields = [
            'store_id' => 'magento_store_id',
            'store_code' => 'magento_store_code',
            'store_currency' => 'magento_currency',
        ];

        $this->assertSame([
            'magento_store_id' => '2',
            'magento_store_code' => 'main',
            'magento_currency' => 'EUR',
        ], $this->customAttributes($fields, self::store('main_en', 'en_US')));
    }

    public static function storeViewsOfOneStore(): array
    {
        return [
            'english view' => ['main_en', 'en_US'],
            'french view' => ['main_fr', 'fr_FR'],
        ];
    }

    #[DataProvider('storeViewsOfOneStore')]
    public function testStoreViewsOfOneStoreSendTheirOwnViewCodeAndLocale(string $viewCode, string $locale): void
    {
        $attributes = $this->customAttributes(self::ALL_FIELDS, self::store($viewCode, $locale));

        $this->assertSame('main', $attributes['store_code']);
        $this->assertSame('2', $attributes['store_id']);
        $this->assertSame($viewCode, $attributes['store_view_code']);
        $this->assertSame($locale, $attributes['store_locale']);
    }

    public function testInheritedLocaleIsSent(): void
    {
        // getStoreLocale() returns the website's locale for a store view without its own (see ConfigTest)
        $fields = ['store_locale' => 'language'];

        $this->assertSame(
            ['language' => 'de_DE'],
            $this->customAttributes($fields, self::store('main_de', 'de_DE'))
        );
    }

    public function testNoFieldsSendNothing(): void
    {
        $this->assertSame([], $this->customAttributes([], self::store('main_en', 'en_US')));
    }
}
