<?php

namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class CreateOrderAfter implements ObserverInterface
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
     * @var CartRepositoryInterface
     */
    private $quoteRepository;
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * CreateOrderAfter constructor.
     *
     * @param Data $helper
     * @param OrderRepositoryInterface $orderRepository
     * @param CartRepositoryInterface $quoteRepository
     * @param SerializerInterface $serializer
     */
    public function __construct(
        Data $helper,
        OrderRepositoryInterface $orderRepository,
        CartRepositoryInterface $quoteRepository,
        SerializerInterface $serializer
    ) {
        $this->helper = $helper;
        $this->orderRepository = $orderRepository;
        $this->quoteRepository = $quoteRepository;
        $this->serializer = $serializer;
    }

    /**
     * Order Create event to trueloyal
     *
     * @param Observer $observer
     * @return void
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $event = $this->helper->getRewardEvents();
        $orderId = $observer->getEvent()->getOrder()->getId();
        $order = $this->orderRepository->get($orderId);
        $trueloyalOrder = $this->helper->getTrueLoyalOrderByOrderId($orderId);
        $replacedOrderId = $order->getIncrementId();
        if (!$trueloyalOrder->getTrueLoyalReward()) {
            if ($order->getQuoteId() !== null) {
                $quote = $this->quoteRepository->get($order->getQuoteId());
                $trueloyalQuote = $this->helper->getTrueLoyalQuoteByQuoteId($order->getQuoteId());
            }
            if ($order->getQuoteId() !== null && $trueloyalQuote->getRedeemRewardDiscount()) {
                $redeemReward = $trueloyalQuote->getRedeemRewardDiscount();
                $rewardData = $this->helper->getRewardRulesData($quote, $redeemReward);
                $url = $this->helper->getLiveWebHookUrl() . "transactions/" . $rewardData['id'] . "/approve";
                $this->helper->request($url, "", "post", "live_api");
                $trueloyalOrder->setTrueLoyalReward($this->serializer->serialize($rewardData))->setOrderId($orderId);
            } else {
                $trueloyalOrder->setTrueLoyalReward("{}")->setOrderId($orderId);
            }
            try {
                $trueloyalOrder->save();
            } catch (CouldNotSaveException $e) {
                $this->helper->addErrorLog($e->getMessage());
            }
        }
        $this->helper->createTrueLoyalOrder($orderId, $replacedOrderId);
    }
}
