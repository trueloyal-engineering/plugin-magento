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

    /**
     * Decode json response and handle response Data
     *
     * @param mixed $responseData
     * @return array|bool[]
     */
    public function returnResponseData($responseData): array
    {
        $response = (!empty($responseData) && $this->isJson($responseData)) ?
            $this->json->unserialize($responseData) : [];
        if (!empty($response)) {
            if (!isset($response['error_code']) && !isset($response['error'])) {
                $result = [
                    "success" => true,
                    "result" => $response
                ];
            } else {
                $result = [
                    "success" => false,
                    "result" => $response['reason']
                ];
            }
        } else {
            $result = [
                "success" => false,
                "result" => __("Something went wrong, check and try again")
            ];
        }
        return $result;
    }
}
