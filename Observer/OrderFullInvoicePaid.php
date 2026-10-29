<?php

namespace TrueLoyal\LoyaltyRewards\Observer;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use TrueLoyal\LoyaltyRewards\Helper\Data;
use TrueLoyal\LoyaltyRewards\Helper\OrderComplete;

class OrderFullInvoicePaid implements ObserverInterface
{
    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;
    /**
     * @var Data
     */
    private $helper;
    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;
    /**
     * @var Http
     */
    private $request;
    /**
     * @var OrderFactory
     */
    private $orderFactory;
    /**
     * @var RestRequest
     */
    private $restRequest;
    /**
     * @var OrderComplete
     */
    private $orderCompleteHelper;

    /**
     * OrderFullInvoicePaid constructor.
     *
     * @param Data $helper
     * @param OrderComplete $orderCompleteHelper
     * @param Http $request
     * @param OrderFactory $orderFactory
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RestRequest $restRequest
     */
    public function __construct(
        Data $helper,
        OrderComplete $orderCompleteHelper,
        Http $request,
        OrderFactory $orderFactory,
        InvoiceRepositoryInterface $invoiceRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RestRequest $restRequest
    ) {
        $this->helper = $helper;
        $this->request = $request;
        $this->orderFactory = $orderFactory;
        $this->invoiceRepository = $invoiceRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->restRequest = $restRequest;
        $this->orderCompleteHelper = $orderCompleteHelper;
    }

    /**
     * Send order paid event to trueloyal
     *
     * @param Observer $observer
     * @return bool
     */
    public function execute(Observer $observer)
    {
        $event = $this->helper->getRewardEvents();
        $orderId = $this->request->getParam('order_id') ?? $this->restRequest->getParam('orderId');
        $order = $this->orderFactory->create()->load($orderId);
        if (in_array('order_paid', $event, true) && !$order->canInvoice()) {
            $this->orderFullInvoiceData($order, $orderId);
        }
        $this->orderCompleteHelper->getCompletedOrder($order);
        return true;
    }

    /**
     * Order Full Invoice Data
     *
     * @param mixed $order
     * @param mixed $orderId
     * @return bool
     */
    public function orderFullInvoiceData($order, $orderId)
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder->addFilter('order_id', $orderId)->create();
            $invoices = $this->invoiceRepository->getList($searchCriteria);
            $invoicesItems = $invoices->toArray();
            $couponCode = $this->helper->getCouponCodes($order);
            $replacedOrderId = $this->helper->getReplacedOrderID($orderId);
            foreach ($invoicesItems["items"] as $key => $invoiceItem) {
                $invoicesItems["items"][$key] = $this->helper->setFormatedPrice($invoicesItems["items"][$key]);
                $invoicesItems["items"][$key]['total_qty'] = (int) $invoicesItems["items"][$key]['total_qty'];
                $invoicesItems["items"][$key]['discount_amount'] = $invoicesItems["items"][$key]['discount_amount'];
                $invoicesItems["items"][$key]['base_discount_amount'] = $invoicesItems["items"][$key]['base_discount_amount'];
                $invoicesItems["items"][$key]["order_id"] = $replacedOrderId;
                /*To get full item data we have to load repository invoice item data*/
                $invoiceData = $this->invoiceRepository->get($invoiceItem["entity_id"]);
                foreach ($invoiceData->getItems() as $item) {
                    unset($item['invoice']);
                    $invoiceItemData = $item->debug();
                    $invoiceItemData['qty'] = (int) $invoiceItemData['qty'];
                    $invoiceItemData = $this->helper->setFormatedPrice($invoiceItemData);
                    $productId = $invoiceItemData['product_id'];
                    /*Product url and product image url not availale from Invoice item so we have to load product to get an additional required data*/
                    $productInfo = $this->helper->getProductUrlAndImageUrl($productId);
                    $invoiceItemData['product_url'] = $productInfo['product_url'];
                    $invoiceItemData['product_image_url'] = $productInfo['product_image_url'];
                    $invoiceItemData['category_name'] = $this->helper->getCategoryName($productId);
                    $invoicesItems["items"][$key]['items'][] = $invoiceItemData;
                }
            }
            $invoicesItems['coupon_code'] = $couponCode;
            $invoicesItems['order_id'] = $replacedOrderId;
            $params = [
                "member_id" => $this->helper->getMemberIdentifierValueById($order->getCustomerId(), $order->getCustomerEmail()),
                "activity_id" => "order_paid",
                "data" => $invoicesItems
            ];
            $url = $this->helper->getWebHookUrl();
            $params = $this->helper->json->serialize($params);
            $this->helper->request($url, $params, "post");
            return true;
        } catch (Exception $e) {
            $this->helper->logger->critical($e->getMessage());
            return false;
        }
    }
}
