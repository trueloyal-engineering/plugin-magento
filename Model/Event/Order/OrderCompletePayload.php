<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\Event\Order;

use Magento\Sales\Model\Order;

/**
 * order_complete: the complete order, item discounts as recorded on the order items
 */
class OrderCompletePayload
{
    public function __construct(private readonly Formatter $formatter)
    {
    }

    public function build(Order $order): array
    {
        $order->getPayment()->setMethodInstance();
        $incrementId = (string)$order->getIncrementId();
        $data = $order->toArray();
        $data['base_subtotal_invoiced'] = $data['base_subtotal'];
        $data['subtotal_invoiced'] = $data['subtotal'];
        $data = $this->formatter->withPayment($this->formatter->prices($data), $order);
        unset($data['items']);
        [, , $total, $baseTotal] = $this->formatter->discountsBySku($order);
        foreach ($order->getItems() as $item) {
            unset($item['product']);
            $itemData = $this->formatter->orderItem($item->debug(), $incrementId);
            $data['items'][] = $itemData + $this->formatter->productDetails($itemData['product_id']);
        }
        $addresses = $data['addresses'] ?? [];
        unset($data['addresses']);
        foreach ($addresses as $address) {
            $data['addresses'][] = $address;
        }
        $data['discount_amount'] = $total;
        $data['base_discount_amount'] = $baseTotal;
        $data['entity_id'] = $incrementId;
        $data['order_id'] = $incrementId;
        $data['coupon_code'] = $this->formatter->couponCodes($order);
        $data['total_qty_ordered'] = (int)$data['total_qty_ordered'];
        return $data;
    }
}
