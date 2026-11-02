<?php

namespace TrueLoyal\LoyaltyRewards\Helper;

class Reward extends Config
{
    /**
     * Get Collect Reward Value Data
     *
     * @param mixed $orderId
     * @param mixed $flag
     * @param mixed $actionData
     * @return $this
     */
    public function getCollectRewardValueData($orderId, $flag, $actionData)
    {
        $order = $this->orderRepository->get($orderId);
        $quoteID = $order->getQuoteId();
        $quote = $this->quoteFactory->create()->load($quoteID);
        $trueloyalQuote = $this->getTrueLoyalQuoteByQuoteId($quote->getId());
        $redeemReward = $trueloyalQuote->getRedeemRewardDiscount();
        $rewardData = $this->getRewardRulesData($quote, $redeemReward);
        if (isset($rewardData['rule'])
            && ($rewardData['rule'] == 'fixed_amount_discount'
                || $rewardData['rule'] == 'percentage_discount'
                || $rewardData['rule'] == 'flexible_points_reward')) {

            $totalAmount = $order->getSubtotal();
            if ($rewardData['rule'] == 'fixed_amount_discount') {
                $totalAmount = $rewardData['reward_value'];
            } else if ($rewardData['rule'] == 'flexible_points_reward') {
                $totalAmount = ($rewardData['points_to_be_redeemed'] / $rewardData['conversion_rate']);
            } else {
                $totalAmount = $totalAmount * $rewardData['reward_value'] / 100;
            }
            if ($flag == 'creditmemeo') {
                $creditMemoId = '';
                foreach ($order->getCreditmemosCollection() as $creditMemo) {
                    $creditMemoId = $creditMemo->getIncrementId();
                }
                if (!$creditMemoId) {
                    $actionData->setGrandTotal($actionData->getGrandTotal() - $totalAmount);
                    $actionData->setBaseGrandTotal($actionData->getBaseGrandTotal() - $totalAmount);
                }
            } else {
                $invoiceId = '';
                foreach ($order->getInvoiceCollection() as $invoice) {
                    $invoiceId = $invoice->getIncrementId();
                }
                if (!$invoiceId) {
                    $actionData->setGrandTotal($actionData->getGrandTotal() - $totalAmount);
                    $actionData->setBaseGrandTotal($actionData->getBaseGrandTotal() - $totalAmount);
                }
            }
            return $this;
        }
    }
}
