<?php

namespace TrueLoyal\LoyaltyRewards\Model\Total\CreditMemo;

use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;
use TrueLoyal\LoyaltyRewards\Helper\Reward;

class LoyaltyRewardsDiscount extends AbstractTotal
{
    /**
     * @var Reward
     */
    private $helper;

    /**
     * LoyaltyRewardsDiscount constructor.
     *
     * @param Reward $helper
     */
    public function __construct(
        Reward $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * CreditMemo show discount show in refund page
     *
     * @param Creditmemo $creditMemo
     * @return Reward
     */
    public function collect(Creditmemo $creditMemo)
    {
        // if ($creditMemo->getTotalQty() > 0) {
        //     $orderId = $creditMemo->getOrderId();
        //     return $this->helper->getCollectRewardValueData($orderId, 'creditmemeo', $creditMemo);
        // }
        return $this;
    }
}
