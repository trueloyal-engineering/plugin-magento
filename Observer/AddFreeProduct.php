<?php

namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class AddFreeProduct implements ObserverInterface
{
    /**
     * @var Http
     */
    protected $request;

    /**
     * @var Data
     */
    protected $helper;
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * Add free product constructor.
     *
     * @param Http $request
     * @param CheckoutSession $checkoutSession
     * @param Data $helper
     */
    public function __construct(
        Http $request,
        CheckoutSession $checkoutSession,
        Data $helper
    ) {
        $this->request = $request;
        $this->checkoutSession = $checkoutSession;
        $this->helper = $helper;
    }

    /**
     * Set Free Product To Quote
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $item = $observer->getEvent()->getData('quote_item');
        $quote = $this->checkoutSession->getQuote();
        $redeemReward = $this->request->getPost('redeem_reward');
        if (isset($redeemReward)) {
            $rewardData = $this->helper->getRewardRulesData($quote, $redeemReward);
            if (isset($rewardData["rule"]) && $rewardData["rule"] == 'product_redemption') {
                $item = ($item->getParentItem() ?: $item);
                $price = 00.00;
                $item->setCustomPrice($price);
                $item->setOriginalCustomPrice($price);
                $item->getProduct()->setIsSuperMode(true);
            }
        }
    }
}
