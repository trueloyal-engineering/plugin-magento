<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\Event\Order;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * order_paid: every invoice of the order, with its items
 */
class OrderPaidPayload
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly Formatter $formatter
    ) {
    }

    public function build(Order $order): array
    {
        $incrementId = (string)$order->getIncrementId();
        $data = $this->invoiceRepository->getList(
            $this->searchCriteriaBuilder->addFilter('order_id', $order->getEntityId())->create()
        )->toArray();
        foreach ($data['items'] as $key => $invoice) {
            $data['items'][$key] = $this->formatter->prices($data['items'][$key]);
            $data['items'][$key]['total_qty'] = (int)$data['items'][$key]['total_qty'];
            $data['items'][$key]['order_id'] = $incrementId;
            foreach ($this->invoiceRepository->get($invoice['entity_id'])->getItems() as $item) {
                unset($item['invoice']);
                $itemData = $item->debug();
                $itemData['qty'] = (int)$itemData['qty'];
                $itemData = $this->formatter->prices($itemData);
                $data['items'][$key]['items'][] = $itemData + $this->formatter->productDetails($itemData['product_id']);
            }
        }
        $data['coupon_code'] = $this->formatter->couponCodes($order);
        $data['order_id'] = $incrementId;
        return $data;
    }
}
