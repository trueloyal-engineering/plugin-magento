<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\Event\Order;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use TrueLoyal\LoyaltyRewards\Helper\Config;
use TrueLoyal\LoyaltyRewards\Model\Reward\DiscountCalculator;

/**
 * order_create: the placed order, with each item's discount including its share of the redeemed TrueLoyal reward
 *
 * The reward's share is also saved to the order items' discount_amount, so build this once per order.
 */
class OrderCreatePayload
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly Config $config,
        private readonly Formatter $formatter,
        private readonly DiscountCalculator $discountCalculator
    ) {
    }

    public function build(int|string $orderId): array
    {
        /** @var Order $order */
        $order = $this->orderRepository->get($orderId);
        $incrementId = (string)$order->getIncrementId();
        $order->setCouponCode($this->formatter->couponCodes($order));
        $order->getPayment()->setMethodInstance();
        $data = $this->formatter->withPayment($order->debug(), $order);
        $data = $this->formatter->prices($data);
        $data['entity_id'] = $incrementId;
        $data['order_id'] = $incrementId;
        $data['base_discount_amount'] = abs($data['base_discount_amount']);
        $data['discount_amount'] = abs($data['discount_amount']);
        unset($data['items']);
        $total = $baseTotal = 0;
        foreach ($order->getItems() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }
            $quote = $this->quoteRepository->get($order->getQuoteId());
            $trueloyalQuote = $this->config->getTrueLoyalQuoteByQuoteId($order->getQuoteId());
            if (!empty($trueloyalQuote->getRedeemRewardDiscount())) {
                $reward = $this->config->getRewardRulesData($quote, $trueloyalQuote->getRedeemRewardDiscount());
                if ($reward) {
                    [$discount, $baseDiscount] = $this->addRewardDiscount($item, $reward, $quote);
                    $total += $discount;
                    $baseTotal += $baseDiscount;
                }
            } else {
                $total += $item->getDiscountAmount();
                $baseTotal += $item->getBaseDiscountAmount();
            }
            $itemData = $this->formatter->orderItem($item->debug(), $incrementId);
            $itemData += $this->formatter->productLinks($itemData['product_id']);
            $categories = $this->formatter->categories($itemData['product_id']);
            $itemData['category_name'] = $categories['name'];
            $itemData['category_ids'] = $categories['ids'];
            $data['items'][] = $itemData;
        }
        $data['discount_amount'] = $total;
        $data['base_discount_amount'] = $baseTotal;
        $data = $this->formatter->relist($data, 'addresses');
        $data = $this->formatter->relist($data, 'status_histories');
        $data['total_qty_ordered'] = (int)$data['total_qty_ordered'];
        return $data;
    }

    /**
     * Add the item's share of a discount reward to its discount_amount and save the item
     *
     * Percentage rewards apply to each item; fixed and flexible-points rewards are spread by the item's share of the
     * subtotal. Unsupported reward types (free shipping, gift) leave the item and the totals unchanged.
     *
     * @return array{0: float|int, 1: float|int} new discount and base discount, 0 when unchanged
     */
    private function addRewardDiscount(Item $item, array $reward, $quote): array
    {
        $value = $this->discountCalculator->value($reward);
        if ($reward['rule'] == 'percentage_discount') {
            $discount = $item->getDiscountAmount() + ($item->getPrice() * $item->getQtyOrdered()) * $value / 100;
            $baseDiscount = $item->getBaseDiscountAmount()
                + ($item->getBasePrice() * $item->getQtyOrdered()) * $value / 100;
        } elseif ($reward['rule'] == 'fixed_amount_discount' || $reward['rule'] == 'flexible_points_reward') {
            $share = ($item->getPrice() * $item->getQtyOrdered()) / $quote->getSubtotal();
            $baseShare = ($item->getBasePrice() * $item->getQtyOrdered()) / $quote->getBaseSubtotal();
            $discount = $item->getDiscountAmount() + $share * $value;
            $baseDiscount = $item->getBaseDiscountAmount() + $baseShare * $value;
        } else {
            return [0, 0];
        }
        $item->setDiscountAmount(number_format($discount, 2, '.', ''));
        $item->setBaseDiscountAmount(number_format($baseDiscount, 2, '.', ''));
        $item->save();
        return [$discount, $baseDiscount];
    }
}
