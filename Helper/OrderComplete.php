<?php

namespace TrueLoyal\LoyaltyRewards\Helper;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class OrderComplete extends \Magento\Framework\App\Helper\AbstractHelper
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
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * Order Complete constructor.
     *
     * @param Data $helper
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        Data $helper,
        OrderRepositoryInterface $orderRepository,
        OrderFactory $orderFactory
    ) {
        $this->helper = $helper;
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
    }

    /**
     * Order complete event to trueloyal
     *
     * @param $order
     * @return bool
     * @throws NoSuchEntityException
     */
    public function getCompletedOrder($order)
    {
        $event = $this->helper->getRewardEvents();
        $orderId = $order->getEntityId();
        $trueloyalOrder = $this->helper->getTrueLoyalOrderByOrderId($orderId);
        if (in_array('order_complete', $event, true) &&
            $order->getState() == 'complete' &&
            $trueloyalOrder->getCompleteRequestSent() == 0
        ) {
            $order = $this->orderRepository->get($orderId);
            $order->getPayment()->setMethodInstance();
            $orderData = $order->toArray();
            $orderData['base_subtotal_invoiced'] = $orderData['base_subtotal'];
            $orderData['subtotal_invoiced'] = $orderData['subtotal'];
            $orderData = $this->helper->setFormatedPrice($orderData);
            $orderData['payment'] = $order->getPayment()->debug();
            unset($orderData['payment (Magento\Sales\Model\Order\Payment\Interceptor)']);
            $orderData['payment'] = $this->helper->setFormatedPrice($orderData['payment']);
            $replacedOrderId = $this->helper->getReplacedOrderID($order->getEntityId());
            unset($orderData['items']);
            $totalDiscountAmount = 0;
            $totalBaseDiscountAmount = 0;
            foreach ($order->getAllItems() as $item) {
                if ($item->getParentItemId()) {
                    continue;
                }
                $discAmount[$item->getSku()] = (int)$item->getDiscountAmount();
                $totalDiscountAmount += $discAmount[$item->getSku()];
            
                $discBaseAmount[$item->getSku()] = (int)$item->getBaseDiscountAmount();
                $totalBaseDiscountAmount += $discBaseAmount[$item->getSku()];
            }
            foreach ($order->getItems() as $item) {
                unset($item['product']);
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
                $productId = $orderItemData['product_id'];
                /*Product url and product image url not availale from Order item so we have to load product to get an additional required data*/
                $productInfo = $this->helper->getProductUrlAndImageUrl($productId);
                $orderItemData['product_url'] = $productInfo['product_url'];
                $orderItemData['product_image_url'] = $productInfo['product_image_url'];
                $orderItemData['category_name'] = $this->helper->getCategoryName($productId);
                $orderData['items'][] = $orderItemData;
            }
            $addressesData = $orderData["addresses"] ?? [];
            unset($orderData["addresses"]);
            foreach ($addressesData as $key => $address) {
                $orderData["addresses"][] = $address;
            }
            $orderData['discount_amount'] = $totalDiscountAmount ?? 0.00;
            $orderData['base_discount_amount'] = $totalBaseDiscountAmount ?? 0.00;
            $orderData["entity_id"] = $replacedOrderId;
            $orderData["order_id"] = $replacedOrderId;
            $couponCode = $this->helper->getCouponCodes($order);
            $orderData['coupon_code'] = $couponCode;
            $orderData['total_qty_ordered'] = (int)$orderData['total_qty_ordered'];
            $params = [
                "member_id" => $this->helper->getMemberIdentifierValueById($order->getCustomerId(), $order->getCustomerEmail()),
                "activity_id" => "order_complete",
                "data" => $orderData
            ];
            $url = $this->helper->getWebHookUrl();
            $params = $this->helper->json->serialize($params);
            $this->helper->request($url, $params, "post");
            try {
                /*Set complete request send to TrueLoyal*/
                $trueloyalOrder->setCompleteRequestSent(1);
                $trueloyalOrder->save();
            } catch (CouldNotSaveException $e) {
                $this->helper->addErrorLog($e->getMessage());
            }
            return true;
        }
    }
}
