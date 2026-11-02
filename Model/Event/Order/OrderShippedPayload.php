<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\Event\Order;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * order_shipped: every shipment of the order with its items and tracks, plus the order's discounts and totals
 *
 * Shipment items carry the discount of their order item (by SKU); the order-level discount counts each item once,
 * however many parcels it was shipped in.
 */
class OrderShippedPayload
{
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly Formatter $formatter
    ) {
    }

    public function build(Order $order): array
    {
        $incrementId = (string)$order->getIncrementId();
        $data = $this->shipmentRepository->getList(
            $this->searchCriteriaBuilder->addFilter('order_id', $order->getEntityId())->create()
        )->toArray();
        [$bySku, $baseBySku, $total, $baseTotal] = [[], [], 0.00, 0.00];
        foreach ($data['items'] as $key => $shipment) {
            $data['items'][$key]['total_qty'] = (int)$data['items'][$key]['total_qty'];
            $data = $this->formatter->prices($data);
            $data['items'][$key]['order_id'] = $incrementId;
            $shipmentModel = $this->shipmentRepository->get($shipment['entity_id']);
            [$bySku, $baseBySku, $total, $baseTotal] = $this->formatter->discountsBySku($order);
            foreach ($shipmentModel->getItems() as $item) {
                unset($item['shipment']);
                $itemData = $item->debug();
                $itemData['qty'] = (int)$itemData['qty'];
                $itemData['price'] = (float)$itemData['price'];
                $itemData['base_price'] = (float)$itemData['price'];
                $itemData = $this->formatter->prices($itemData);
                $itemData['discount_amount'] = $bySku[$item->getSku()] ?? 0.00;
                $itemData['base_discount_amount'] = $baseBySku[$item->getSku()] ?? 0.00;
                $data['items'][$key]['items'][] = $itemData + $this->formatter->productDetails($itemData['product_id']);
            }
            foreach ($shipmentModel->getTracks() as $track) {
                $trackData = $track->debug();
                $trackData['order_id'] = $incrementId;
                $data['items'][$key]['track'][] = $trackData;
            }
        }
        $data['coupon_code'] = $this->formatter->couponCodes($order);
        $data['order_id'] = $incrementId;
        $data['discount_amount'] = $total;
        $data['base_discount_amount'] = $baseTotal;
        $data['order_subtotal'] = (float)$order->getSubtotal();
        $data['order_base_subtotal'] = (float)$order->getBaseSubtotal();
        $data['order_grand_total'] = (float)$order->getGrandTotal();
        $data['order_base_grand_total'] = (float)$order->getBaseGrandTotal();
        return $data;
    }
}
