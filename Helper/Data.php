<?php

namespace TrueLoyal\LoyaltyRewards\Helper;

use Exception;
use Magento\Customer\Api\Data\CustomerInterface;

class Data extends Config
{
    /**
     * Get Redeem Reward Discount Data
     *
     * @param string $orderId
     * @return array
     */
    public function getRedeemRewardDiscountData($orderId)
    {
        $order = $this->orderRepository->get($orderId);
        $quoteID = $order->getQuoteId();
        $quote = $this->quoteFactory->create()->load($quoteID);
        $trueloyalQuote = $this->getTrueLoyalQuoteByQuoteId($quote->getId());
        $redeemReward = $trueloyalQuote->getRedeemRewardDiscount();
        $rewardData = $this->getRewardRulesData($quote, $redeemReward);
        $discountAmount = "";
        $discountLabel = "";
        if ($this->discountCalculator()->isDiscount($rewardData)) {
            $discountAmount = $this->discountCalculator()->amount($rewardData, $order->getSubtotal());
            $discountLabel = $this->getRewardAppliedRuleLabel($quote);
        }
        return [
            "status" => $discountAmount == "" ? false : true,
            "value" => $discountAmount,
            "label" => $discountLabel
        ];
    }

    /**
     * Get member identifier value from a customer ID and email (for order/customer event context).
     * Returns formatted ID when setting is member_id, email otherwise.
     * Falls back to email when customer ID is absent (guest).
     *
     * @param int|null $customerId
     * @param string|null $email
     * @return string
     */
    public function getMemberIdentifierValueById($customerId, $email = null): string
    {
        return $this->memberIdResolver->forCustomer($customerId, $email);
    }

    /**
     * Get Customer Email By Id
     *
     * @param mixed $customerId
     * @return string
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getCustomerEmailById($customerId)
    {
        if ($customerId) {
            try {
                return $this->getCustomerById($customerId)->getEmail();
            } catch (Exception $e) {
                $this->logger->addErrorLog($e->getMessage());
            }
        }
        return "";
    }

    /**
     * Get Customer By Id
     *
     * @param mixed $customerId
     * @return CustomerInterface|string
     */
    public function getCustomerById($customerId)
    {
        if ($customerId) {
            try {
                return $this->customerRepository->getById($customerId);
            } catch (Exception $e) {
                $this->logger->addErrorLog($e->getMessage());
            }
        }
        return "";
    }

    /**
     * Send Reject reward point API request to TrueLoyal when cart gone Abandoned or Redeem Cancel button clicked
     *
     * @param Quote $quote
     * @return bool
     */
    public function sendRejectRewardRequest($quote)
    {
        try {
            $trueloyalQuote = $this->getTrueLoyalQuoteByQuoteId($quote->getId());
            $redeemReward = $trueloyalQuote->getRedeemRewardDiscount();
            $rewardData = $this->getRewardRulesData($quote, $redeemReward);
            if (!empty($rewardData)) {
                $this->rewardService->reject((string)$rewardData['id']);
                $items = $quote->getAllItems();
                foreach ($items as $item) {
                    $trueloyalQuoteItem = $this->getTrueLoyalQuoteItemByItemId($item->getId());
                    if ($trueloyalQuoteItem->getData('is_trueloyal_free_product')) {
                        $item->delete();
                        break;
                    }
                }
                $trueloyalQuote->setRedeemRewardDiscount('');
                $trueloyalQuote->setRewardRulesData('');
                $trueloyalQuote->save();
                return true;
            }
        } catch (Exception $e) {
            $this->logger->addErrorLog($e->getMessage());
        }
        return false;
    }

    /**
     * Set formated price and return updated array
     *
     * @param array $priceData
     * @return array|string[]
     */
    public function setFormatedPrice($priceData)
    {
        foreach ($priceData as $key => $itemData) {
            if ((str_contains($key, 'price') || str_contains($key, 'amount') ||
                    str_contains($key, 'rate') || str_contains($key, 'incl') ||
                    str_contains($key, 'discount') || str_contains($key, 'base_shipping') ||
                    str_contains($key, 'base_tax') || str_contains($key, 'row_') ||
                    str_contains($key, 'shipping_invoiced') || str_contains($key, 'tax_invoiced') ||
                    str_contains($key, 'shipping_captured') || str_contains($key, 'total')
                ) && $itemData != ""
            ) {
                $priceData[$key] = (float)$itemData;
            }
        }
        return $priceData;
    }

    /**
     * Get product url and Image Url
     *
     * @param mixed $id
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getProductUrlAndImageUrl($id)
    {
        $product = $this->productRepository->getById($id);
        return [
            'product_url' => $product->getProductUrl(),
            'product_image_url' => $this->getImageFullUrl($product)
        ];
    }

    /**
     * Get Image Full Url
     *
     * @param mixed $product
     * @return string
     * @throws NoSuchEntityException
     */
    public function getImageFullUrl($product)
    {
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl("media");
        if ($product->getImage()) {
            return $mediaUrl . 'catalog/product' . $product->getImage();
        }
        $imageData = $this->helperImageFactory->create();
        return $this->assetRepos->getUrl($imageData->getPlaceholder('small_image'));
    }

    /**
     * Get Category Name
     *
     * @param mixed $productId
     * @throws NoSuchEntityException
     */
    public function getCategoryName($productId)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $categoryIds = $this->productCategory->getCategoryIds($productId);
        $categoryName = '';
        if ($categoryIds) {
            $lastCategoryId = end($categoryIds);
            $categoryInstance = $this->categoryRepository->get($lastCategoryId, $storeId);
            $categoryName = $categoryInstance->getName();
        }
        return $categoryName;
    }

    /**
     * Check API header api-key and partner-id with configured auth key
     *
     * Missing headers and unconfigured keys never match, and the comparison is constant-time.
     *
     * @param mixed $requestedApiKey
     * @param mixed $requestedPartnerId
     * @return bool
     */
    public function isValidateApiAuth($requestedApiKey, $requestedPartnerId)
    {
        $configuredApiKey = (string)$this->getApiKey();
        $configuredPartnerId = (string)$this->getPartnerId();
        if (!is_string($requestedApiKey) || !is_string($requestedPartnerId) ||
            $configuredApiKey === '' || $configuredPartnerId === ''
        ) {
            return false;
        }
        return hash_equals($configuredApiKey, $requestedApiKey) &&
            hash_equals($configuredPartnerId, $requestedPartnerId);
    }
}
