<?php

namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class OrderCancel implements ObserverInterface
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * Order Cancel constructor.
     *
     * @param Data $helper
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Data $helper,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->helper = $helper;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Order cancel event to trueloyal
     *
     * @param Observer $observer
     * @return bool
     */
    public function execute(Observer $observer)
    {
        $event = $this->helper->getRewardEvents();
        if (in_array('order_cancelled', $event, true)) {
            $order = $observer->getEvent()->getOrder();
            $order->getPayment()->setMethodInstance();
            $couponCode = $this->helper->getCouponCodes($order);
            $replacedOrderId = $this->helper->getReplacedOrderID($order->getEntityId());
            $orderData = $order->debug();
            $orderData = $this->helper->setFormatedPrice($orderData);
            $orderData['payment'] = $order->getPayment()->debug();
            unset($orderData['payment (Magento\Sales\Model\Order\Payment\Interceptor)']);
            $orderData['payment'] = $this->helper->setFormatedPrice($orderData['payment']);
            $orderData['coupon_code'] = $couponCode;
            $orderData['order_id'] = $replacedOrderId;
            $orderData["entity_id"] = $replacedOrderId;
            unset($orderData['items']);
            foreach ($order->getItems() as $item) {
                $orderItemData = $item->debug();
                $orderItemData['qty_ordered'] = (int)$orderItemData['qty_ordered'];
                if (isset($orderItemData['product_options']['info_buyRequest']['qty'])) {
                    $buyRequestQty = $orderItemData['product_options']['info_buyRequest']['qty'];
                    $orderItemData['product_options']['info_buyRequest']['qty'] = (int)$buyRequestQty;
                }
                if (isset($orderItemData['qty_canceled'])) {
                    $orderItemData['qty_canceled'] = (int)$orderItemData['qty_canceled'];
                }
                if (isset($orderItemData['qty_invoiced'])) {
                    $orderItemData['qty_invoiced'] = (int)$orderItemData['qty_invoiced'];
                }
                if (isset($orderItemData['qty_refunded'])) {
                    $orderItemData['qty_refunded'] = (int)$orderItemData['qty_refunded'];
                }
                if (isset($orderItemData['qty_shipped'])) {
                    $orderItemData['qty_shipped'] = (int)$orderItemData['qty_shipped'];
                }
                $orderItemData = $this->helper->setFormatedPrice($orderItemData);
                $orderItemData['order_id'] = $replacedOrderId;
                $orderData['items'][] = $orderItemData;
            }
            $orderData['total_qty_ordered'] = (int)$orderData['total_qty_ordered'];
            $params = [
                "member_id" => $this->helper->getMemberIdentifierValueById($order->getCustomerId(), $order->getCustomerEmail()),
                "activity_id" => "order_cancel",
                "data" => $orderData
            ];
            $url = $this->helper->getWebHookUrl();
            $params = $this->helper->json->serialize($params);
            $this->helper->request($url, $params, "post");
            return true;
        }
    }
}
