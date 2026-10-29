<?php

namespace TrueLoyal\LoyaltyRewards\Block\Cart;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use TrueLoyal\LoyaltyRewards\Helper\Reward;
use TrueLoyal\LoyaltyRewards\Logger\Logger as TrueLoyalLogger;

class RewardList extends Template
{
    /**
     * @var Reward
     */
    public $helper;

    /**
     * @var TrueLoyalLogger
     */
    public $logger;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * RewardList constructor.
     *
     * @param Template\Context $context
     * @param Reward $helper
     * @param CustomerSession $customerSession
     * @param TrueLoyalLogger $logger
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Reward $helper,
        CustomerSession $customerSession,
        TrueLoyalLogger $logger,
        array $data = []
    ) {
        $this->logger = $logger;
        $this->customerSession = $customerSession;
        $this->helper = $helper;
        parent::__construct(
            $context,
            $data
        );
    }

    /**
     * Get controller action to redeem reward point
     *
     * @return string
     */
    public function getFormAction()
    {
        return $this->getUrl('loyaltyRewards/loyaltyrewards/applyPoint', ['_secure' => true]);
    }

    /**
     * Check applied rule can be canceled or not
     *
     * @return bool
     */
    public function canCancelRedeem()
    {
        $quote = $this->helper->getQuote();
        $trueloyalQuote = $this->helper->getTrueLoyalQuoteByQuoteId($quote->getId());
        if (!empty($trueloyalQuote->getRewardRulesData() && !empty($trueloyalQuote->getRedeemRewardDiscount()))) {
            return true;
        }
        return false;
    }

    /**
     * Get available reward points
     *
     * @return mixed
     */

    public function getRewardPoints()
    {
        return $this->helper->getRewardPoints();
    }

    /**
     * Get reward point rules
     *
     * @return array
     */
    public function getRedeemRules()
    {
        return $this->helper->getRedeemRules();
    }

    /**
     * Get Customer Email
     *
     * @return string
     */
    public function getCustomerEmail()
    {
        return $this->customerSession->getCustomer()->getEmail();
    }

    /**
     * Get Reward Rules Data
     *
     * @param mixed $quote
     * @param mixed $redeemReward
     * @return mixed
     */
    public function getRewardRulesData($quote, $redeemReward)
    {
        return $this->helper->getRewardRulesData($quote, $redeemReward);
    }
}
