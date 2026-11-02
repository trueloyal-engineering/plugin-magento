<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\Event\Order;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;

/**
 * full_order_refund (one credit memo covering the whole order) and partial_order_refund (any other credit memo)
 */
class RefundPayload
{
    public function __construct(
        private readonly CreditmemoRepositoryInterface $creditmemoRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly Formatter $formatter
    ) {
    }

    public function isFullRefund(Order $order): bool
    {
        return !$order->canCreditmemo() && $order->getCreditmemosCollection()->count() == 1;
    }

    /**
     * Every credit memo of the order, with its items
     */
    public function full(Order $order): array
    {
        $incrementId = (string)$order->getIncrementId();
        $data = $this->creditmemoRepository->getList(
            $this->searchCriteriaBuilder->addFilter('order_id', $order->getEntityId())->create()
        )->toArray();
        foreach ($data['items'] as $key => $creditmemo) {
            $data['items'][$key] = $this->formatter->prices($data['items'][$key]);
            $data['items'][$key]['order_id'] = $incrementId;
            foreach ($this->creditmemoRepository->get($creditmemo['entity_id'])->getItems() as $item) {
                unset($item['refund']);
                $itemData = $this->formatter->prices($item->debug());
                $data['items'][$key]['items'][] = $itemData + $this->formatter->productDetails($itemData['product_id']);
            }
        }
        $data['order_id'] = $incrementId;
        $data['coupon_code'] = $this->formatter->couponCodes($order);
        return $data;
    }

    /**
     * The saved credit memo, with its refunded items (qty > 0)
     */
    public function partial(Creditmemo $creditmemo, Order $order): array
    {
        $creditmemoData = $creditmemo->debug();
        $data['items'][0] = $creditmemoData;
        $data['order_id'] = (string)$order->getIncrementId();
        $data['coupon_code'] = $this->formatter->couponCodes($order);
        unset($data['items'][0]['items']);
        foreach ($creditmemoData['items'] as $item) {
            $item = $this->formatter->prices($item);
            if ($item['qty']) {
                $item['qty'] = (int)$item['qty'];
                $productId = $item['product_id'];
                $links = $this->formatter->productLinks($productId);
                $item['product_url'] = $links['product_url'];
                $item['product_image_url'] = $links['product_image_url'];
                $item['category_name'] = $this->formatter->lastCategoryName($productId);
                $item['category_ids'] = $this->formatter->categories($productId)['ids'];
                $data['items'][0]['items'][] = $item;
            }
        }
        $data['items'][0] = $this->formatter->prices($data['items'][0]);
        return $data;
    }
}
