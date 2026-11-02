<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\Event\Order;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\ImageFactory;
use Magento\Catalog\Model\ProductCategoryList;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Shared formatting of order, invoice, shipment and credit memo data in event payloads
 *
 * The payloads are Model::debug() dumps; the v2 mapping in TrueLoyal reads their fields directly, so keep them as is.
 */
class Formatter
{
    private const PRICE_KEY_PARTS = ['price', 'amount', 'rate', 'incl', 'discount', 'base_shipping', 'base_tax', 'row_',
        'shipping_invoiced', 'tax_invoiced', 'shipping_captured', 'total'];
    private const ITEM_QTY_KEYS = ['qty_canceled', 'qty_invoiced', 'qty_refunded', 'qty_shipped'];

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductCategoryList $productCategoryList,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly ImageFactory $imageHelperFactory,
        private readonly AssetRepository $assetRepository
    ) {
    }

    /**
     * Cast the non-empty price, amount and total fields of one level of data to float
     */
    public function prices(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value != '' && $this->isPriceKey((string)$key)) {
                $data[$key] = (float)$value;
            }
        }
        return $data;
    }

    /**
     * An order item: quantities as integers, prices as floats, order_id = the order's increment ID
     */
    public function orderItem(array $item, string $incrementId): array
    {
        $item['qty_ordered'] = (int)$item['qty_ordered'];
        if (isset($item['product_options']['info_buyRequest']['qty'])) {
            $item['product_options']['info_buyRequest']['qty'] = (int)$item['product_options']['info_buyRequest']['qty'];
        }
        foreach (self::ITEM_QTY_KEYS as $key) {
            if (isset($item[$key])) {
                $item[$key] = (int)$item[$key];
            }
        }
        $item = $this->prices($item);
        $item['order_id'] = $incrementId;
        return $item;
    }

    /**
     * Replace the payment object dump of an order's debug() data with the formatted payment
     */
    public function withPayment(array $orderData, Order $order): array
    {
        $orderData['payment'] = $order->getPayment()->debug();
        unset($orderData['payment (Magento\Sales\Model\Order\Payment\Interceptor)']);
        $orderData['payment'] = $this->prices($orderData['payment']);
        return $orderData;
    }

    /**
     * Turn the debug() dump's keyed lists (addresses, status_histories) into plain lists
     */
    public function relist(array $data, string $key): array
    {
        if (isset($data[$key])) {
            $list = $data[$key];
            unset($data[$key]);
            foreach ($list as $value) {
                $data[$key][] = $value;
            }
        }
        return $data;
    }

    /**
     * Coupon code of the order, as a list
     *
     * @return string[]
     */
    public function couponCodes(Order $order): array
    {
        return $order->getCouponCode() ? [$order->getCouponCode()] : [];
    }

    /**
     * @return array{product_url: string, product_image_url: string}
     */
    public function productLinks(int|string $productId): array
    {
        $product = $this->productRepository->getById($productId);
        return ['product_url' => $product->getProductUrl(), 'product_image_url' => $this->imageUrl($product)];
    }

    /**
     * URL of the product's base image, or of the placeholder
     */
    public function imageUrl(ProductInterface $product): string
    {
        if ($product->getImage()) {
            return $this->storeManager->getStore()->getBaseUrl('media') . 'catalog/product' . $product->getImage();
        }
        return $this->assetRepository->getUrl($this->imageHelperFactory->create()->getPlaceholder('small_image'));
    }

    /**
     * All categories of a product, comma-separated: name = names, ids = IDs
     *
     * @return array{name: string, ids: string}
     */
    public function categories(int|string $productId): array
    {
        $storeId = $this->storeManager->getStore()->getId();
        $names = [];
        $ids = [];
        foreach ($this->productCategoryList->getCategoryIds((int)$productId) ?: [] as $categoryId) {
            $category = $this->categoryRepository->get($categoryId, $storeId);
            $names[] = $category->getName();
            $ids[] = $category->getId();
        }
        return ['name' => implode(',', array_unique($names)), 'ids' => implode(',', array_unique($ids))];
    }

    /**
     * Name of the product's last category, '' without categories
     */
    public function lastCategoryName(int|string $productId): string
    {
        $categoryIds = $this->productCategoryList->getCategoryIds((int)$productId);
        if (!$categoryIds) {
            return '';
        }
        return (string)$this->categoryRepository->get(end($categoryIds), $this->storeManager->getStore()->getId())
            ->getName();
    }

    /**
     * Product links, last category name and all category IDs: what invoice, shipment and credit memo items carry
     */
    public function productDetails(int|string $productId): array
    {
        return $this->productLinks($productId) + [
            'category_name' => $this->lastCategoryName($productId),
            'category_ids' => $this->categories($productId)['ids'],
        ];
    }

    /**
     * Discounts of the order's top-level items by SKU, and their totals (multi-parcel shipments count them once)
     *
     * @return array{0: array<string, float>, 1: array<string, float>, 2: float|int, 3: float|int}
     *     discount by SKU, base discount by SKU, total, base total
     */
    public function discountsBySku(Order $order): array
    {
        $bySku = $baseBySku = [];
        $total = $baseTotal = 0;
        foreach ($order->getAllItems() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }
            $bySku[$item->getSku()] = (float)$item->getDiscountAmount();
            $total += $bySku[$item->getSku()];
            $baseBySku[$item->getSku()] = (float)$item->getBaseDiscountAmount();
            $baseTotal += $baseBySku[$item->getSku()];
        }
        return [$bySku, $baseBySku, $total, $baseTotal];
    }

    private function isPriceKey(string $key): bool
    {
        foreach (self::PRICE_KEY_PARTS as $part) {
            if (str_contains($key, $part)) {
                return true;
            }
        }
        return false;
    }
}
