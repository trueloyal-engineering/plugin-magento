<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\Event\Cart;

use Magento\Quote\Model\Quote;
use TrueLoyal\LoyaltyRewards\Model\Event\Order\Formatter;

/**
 * cart_abandonment: the quote with its items, each with the product's links and last category
 */
class AbandonedCartPayload
{
    public function __construct(private readonly Formatter $formatter)
    {
    }

    public function build(Quote $quote): array
    {
        $items = [];
        foreach ($quote->getItemsCollection() as $quoteItem) {
            $product = $quoteItem->getProduct();
            $quoteItem->setData('product_url', $product->getProductUrl());
            $quoteItem->setData('product_image_url', $this->formatter->imageUrl($product));
            $quoteItem->setData('category_name', $this->formatter->lastCategoryName($product->getEntityId()));
            $quoteItem->unsetData('stock_state_result');
            $quoteItem->unsetData('product');
            $items[] = $quoteItem->debug();
        }
        $data = $this->formatter->prices($quote->debug());
        $data['items_qty'] = (int)$data['items_qty'];
        $data['coupon_code'] = $quote->getCouponCode() ? [$quote->getCouponCode()] : [];
        foreach ($items as $item) {
            $item['qty'] = (int)$item['qty'];
            $data['items'][] = $this->formatter->prices($item);
        }
        return $data;
    }
}
