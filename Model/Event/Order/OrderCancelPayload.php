<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\Event\Order;

use Magento\Sales\Model\Order;

/**
 * order_cancel: the cancelled order (items without product links or categories)
 */
class OrderCancelPayload
{
    public function __construct(private readonly Formatter $formatter)
    {
    }

    public function build(Order $order): array
    {
        $order->getPayment()->setMethodInstance();
        $incrementId = (string)$order->getIncrementId();
        $data = $this->formatter->prices($order->debug());
        $data = $this->formatter->withPayment($data, $order);
        $data['coupon_code'] = $this->formatter->couponCodes($order);
        $data['order_id'] = $incrementId;
        $data['entity_id'] = $incrementId;
        unset($data['items']);
        foreach ($order->getItems() as $item) {
            $data['items'][] = $this->formatter->orderItem($item->debug(), $incrementId);
        }
        $data['total_qty_ordered'] = (int)$data['total_qty_ordered'];
        return $data;
    }
}
