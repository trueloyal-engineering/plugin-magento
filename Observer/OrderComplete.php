<?php

namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class OrderComplete implements ObserverInterface
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
     * @param Observer $observer
     * @return bool
     * @throws CouldNotSaveException
     */
    public function execute(Observer $observer)
    {
        $event = $this->helper->getRewardEvents();
        $order = $observer->getEvent()->getOrder();
        $orderId = $order->getEntityId();
        if (in_array('order_complete', $event, true) &&
            $order->getState() == 'complete' &&
            $order->getCompleteRequestSent() == 0
        ) {
            $order = $this->orderRepository->get($orderId);
            $order->getPayment()->setMethodInstance();
            $orderData = $order->debug();
            $orderData = $this->helper->setFormatedPrice($orderData);
            $orderData['payment'] = $order->getPayment()->debug();
            unset($orderData['payment (Magento\Sales\Model\Order\Payment\Interceptor)']);
            $orderData['payment'] = $this->helper->setFormatedPrice($orderData['payment']);
            $replacedOrderId = $this->helper->getReplacedOrderID($order->getEntityId());
            unset($orderData['items']);
            foreach ($order->getItems() as $item) {
                if ($item->getParentItemId()) {
                    continue;
                }
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
                $orderItemData['product_url'] = $this->helper->getProductUrl($productId);
                $orderItemData['product_image_url'] = $this->helper->getProductImageUrl($productId);
                $categoryData = $this->helper->getCategoryData($productId);
                $orderItemData['category_name'] = $categoryData['name'];
                $orderItemData['category_ids'] = $categoryData['ids'];
                $orderData['items'][] = $orderItemData;
            }
            $addressesData = $orderData["addresses"] ?? [];
            unset($orderData["addresses"]);
            foreach ($addressesData as $key => $address) {
                $orderData["addresses"][] = $address;
            }
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
            /*Set complete request send to TrueLoyal*/
            $orderModel = $this->orderFactory->create()->load($orderId);
            $orderModel->setCompleteRequestSent(1);
            try {
                $orderModel->save();
            } catch (CouldNotSaveException $e) {
                $this->helper->addErrorLog($e->getMessage());
            }
            return true;
        }
    }
}
