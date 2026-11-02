<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Test\Unit\Model\Event\Order;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\ImageFactory;
use Magento\Catalog\Model\ProductCategoryList;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use TrueLoyal\LoyaltyRewards\Model\Event\Order\Formatter;

class FormatterTest extends TestCase
{
    private Formatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new Formatter(
            $this->createMock(ProductRepositoryInterface::class),
            $this->createMock(ProductCategoryList::class),
            $this->createMock(CategoryRepositoryInterface::class),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(ImageFactory::class),
            $this->createMock(Repository::class)
        );
    }

    public function testPricesCastsMoneyFieldsOnly(): void
    {
        $this->assertSame(
            ['price' => 25.0, 'base_discount_amount' => 0.0, 'grand_total' => 60.0, 'tax_percent' => '',
                'sku' => 'TL-1', 'qty' => '2.0000', 'row_total_incl_tax' => null],
            $this->formatter->prices(['price' => '25.0000', 'base_discount_amount' => '0.0000', 'grand_total' => '60',
                'tax_percent' => '', 'sku' => 'TL-1', 'qty' => '2.0000', 'row_total_incl_tax' => null])
        );
    }

    public function testOrderItemCastsQuantitiesAndSetsTheIncrementId(): void
    {
        $item = $this->formatter->orderItem([
            'qty_ordered' => '2.0000', 'qty_invoiced' => '1.0000', 'price' => '10.0000', 'order_id' => '100001',
            'product_options' => ['info_buyRequest' => ['qty' => '2']],
        ], '000000042');
        $this->assertSame(2, $item['qty_ordered']);
        $this->assertSame(1, $item['qty_invoiced']);
        $this->assertSame(2, $item['product_options']['info_buyRequest']['qty']);
        $this->assertSame(10.0, $item['price']);
        $this->assertSame('000000042', $item['order_id']);
        $this->assertArrayNotHasKey('qty_shipped', $item);
    }

    public function testRelistTurnsKeyedDumpsIntoLists(): void
    {
        $this->assertSame(['a' => 1, 'addresses' => [['x'], ['y']]],
            $this->formatter->relist(['addresses' => ['5 (Address)' => ['x'], '6 (Address)' => ['y']], 'a' => 1], 'addresses'));
        $this->assertSame(['a' => 1], $this->formatter->relist(['a' => 1], 'addresses'));
    }
}
