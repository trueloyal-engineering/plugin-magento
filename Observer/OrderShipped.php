<?php

namespace TrueLoyal\LoyaltyRewards\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use TrueLoyal\LoyaltyRewards\Helper\Data;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use TrueLoyal\LoyaltyRewards\Helper\OrderComplete;

class OrderShipped implements ObserverInterface
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var ShipmentRepositoryInterface
     */
    private $shipmentRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var OrderComplete
     */
    private $orderCompleteHelper;

    /**
     * Order shipped constructor.
     *
     * @param Data $helper
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderComplete $orderCompleteHelper
     * @param ShipmentRepositoryInterface $shipmentRepository
     */
    public function __construct(
        Data $helper,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderComplete $orderCompleteHelper,
        ShipmentRepositoryInterface $shipmentRepository
    ) {
        $this->helper = $helper;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->shipmentRepository = $shipmentRepository;
        $this->orderCompleteHelper = $orderCompleteHelper;
    }

    /**
     * Order shipped event to trueloyal
     *
     * @param Observer $observer
     * @return bool
     */
    public function execute(Observer $observer)
    {
        $event = $this->helper->getRewardEvents();
        $shipment = $observer->getEvent()->getShipment();
        $order = $shipment->getOrder();
        $this->orderCompleteHelper->getCompletedOrder($order);
        /*Check the order_shipped event is enabled, if enabled then will send order_shipped event to TrueLoyal*/
        if (in_array('order_shipped', $event)) {
            if (!$order->canShip()) {
                try {
                    $searchCriteria = $this->searchCriteriaBuilder
                        ->addFilter('order_id', $order->getEntityId())->create();
                    $shipment = $this->shipmentRepository->getList($searchCriteria);
                    $shipmentItems = $shipment->toArray();
                    $couponCode = $this->helper->getCouponCodes($order);
                    $replacedOrderId = $this->helper->getReplacedOrderID($order->getEntityId());
                    foreach ($shipmentItems["items"] as $key => $shipmentItem) {
                        $shipmentItems['items'][$key]['total_qty'] = (int)$shipmentItems['items'][$key]['total_qty'];
                        $shipmentItems = $this->helper->setFormatedPrice($shipmentItems);
                        $shipmentItems["items"][$key]["order_id"] = $replacedOrderId;
                        /*To get full shipment item data we have to load repository shipment item data*/
                        $shipmentData = $this->shipmentRepository->get($shipmentItem["entity_id"]);

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


                        foreach ($shipmentData->getItems() as $item) {
                            unset($item['shipment']);
                            $shipmentItemData = $item->debug();
			    $shipmentItemData['qty'] = (int)$shipmentItemData['qty'];
			    $shipmentItemData['price'] = (float)$shipmentItemData['price'];
                            $shipmentItemData['base_price'] = (float)$shipmentItemData['price'];
                            $shipmentItemData = $this->helper->setFormatedPrice($shipmentItemData);
                            $productId = $shipmentItemData['product_id'];
                            $shipmentItemData['discount_amount'] = $discAmount[$item->getSku()] ?? 0.00;
                            $shipmentItemData['base_discount_amount'] = $discBaseAmount[$item->getSku()] ?? 0.00;

                            /*Product url and product image url not availale from shipment item so we have to load product to get an additional required data*/
                            $productInfo = $this->helper->getProductUrlAndImageUrl($productId);
                            $shipmentItemData['product_url'] = $productInfo['product_url'];
                            $shipmentItemData['product_image_url'] = $productInfo['product_image_url'];
                            $shipmentItemData['category_name'] = $this->helper->getCategoryName($productId);
                            $shipmentItems["items"][$key]['items'][] = $shipmentItemData;
                        }
                        foreach ($shipmentData->getTracks() as $track) {
                            $trackData = $track->debug();
                            $trackData["order_id"] = $replacedOrderId;
                            $shipmentItems["items"][$key]['track'][] = $trackData;
                        }
                    }
                    $shipmentItems['coupon_code'] = $couponCode;
                    $shipmentItems['order_id'] = $replacedOrderId;
                    $shipmentItems['discount_amount'] = $totalDiscountAmount ?? 0.00;
                    $shipmentItems['base_discount_amount'] = $totalBaseDiscountAmount ?? 0.00;
                    $shipmentItems['order_subtotal'] = (float)$order->getSubtotal();
                    $shipmentItems['order_base_subtotal'] = (float)$order->getBaseSubtotal();
                    $shipmentItems['order_grand_total'] = (float)$order->getGrandTotal();
                    $shipmentItems['order_base_grand_total'] = (float)$order->getBaseGrandTotal();
                    $params = [
                        "member_id" => $this->helper->getMemberIdentifierValueById($order->getCustomerId(), $order->getCustomerEmail()),
                        "activity_id" => "order_shipped",
                        "data" => $shipmentItems
                    ];
                    $url = $this->helper->getWebHookUrl();
                    $params = $this->helper->json->serialize($params);
                    $this->helper->request($url, $params, "post");
                    return true;
                } catch (Exception $e) {
                    return false;
                }
            }
        }
    }
}
