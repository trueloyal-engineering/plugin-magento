<?php

namespace TrueLoyal\LoyaltyRewards\Controller\LoyaltyRewards;

use Exception;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\Quote;
use TrueLoyal\LoyaltyRewards\Helper\Data;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Checkout\Model\Cart;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use TrueLoyal\LoyaltyRewards\Logger\Logger;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalQuote;

class ApplyPoint implements HttpPostActionInterface
{
    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;
    /**
     * @var Data
     */
    private $helper;
    /**
     * @var ManagerInterface
     */
    private $messageManager;
    /**
     * @var FormKey
     */
    private $formKey;
    /**
     * @var Cart
     */
    private $cart;
    /**
     * @var Product
     */
    private $product;
    /**
     * @var Session
     */
    private $customerSession;
    /**
     * @var SerializerInterface
     */
    private $serializer;
    /**
     * @var Logger
     */
    private $logger;

    /**
     * ApplyPoint construct
     *
     * @param JsonFactory $jsonFactory
     * @param RequestInterface $request
     * @param Data $helper
     * @param ManagerInterface $messageManager
     * @param FormKey $formKey
     * @param Session $customerSession
     * @param Cart $cart
     * @param SerializerInterface $serializer
     * @param Product $product
     * @param CheckoutSession $checkoutSession
     * @param Logger $logger
     */
    public function __construct(
        JsonFactory $jsonFactory,
        RequestInterface $request,
        Data $helper,
        ManagerInterface $messageManager,
        FormKey $formKey,
        Session $customerSession,
        Cart $cart,
        SerializerInterface $serializer,
        Product $product,
        CheckoutSession $checkoutSession,
        Logger $logger
    ) {
        $this->formKey = $formKey;
        $this->cart = $cart;
        $this->product = $product;
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->request = $request;
        $this->messageManager = $messageManager;
        $this->helper = $helper;
        $this->customerSession = $customerSession;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * Apply redeem point rule and save in quote
     *
     * @return ResponseInterface|ResultInterface|mixed
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $redeemReward = $this->request->getPost('redeem_reward');
        $quote = $this->checkoutSession->getQuote();
        /*Managed Set trueloyal quote related to Data to custom table*/
        $this->helper->setAbandonedCartSent($quote->getId(), 2);
        $trueloyalQuote = $this->helper->getTrueLoyalQuoteByQuoteId($quote->getId());
        /*End*/
        $resultJson = $this->jsonFactory->create();
        $this->cart->setUpdatedAt()->save();
        if ($redeemReward == 'cancel') {
            $rewardData = $this->helper->getRewardRulesData($quote);
            $this->helper->sendRejectRewardRequest($quote);
            $this->messageManager->addNotice(
                __('The redeemed %1 is canceled successfully.', $rewardData['reward_name'])
            );
            return $resultJson->setData([
                'success' => true
            ]);
        }
        $rewardRules = $this->helper->getRedeemRules();
        if (!empty($rewardRules)) {
            try {
                $trueloyalQuote->setRewardRulesData($this->helper->jsonSerialize($rewardRules));
                $trueloyalQuote->save();
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
        $rewardData = $this->helper->getRewardRulesData($quote, $redeemReward);
        if ($rewardData["rule"] === "product_redemption" && !empty($rewardData["product_id"])) {
            $productId = $rewardData["product_id"];
            $product = $this->product->load($productId);
            if (!$product->getEntityId() || $product->getStatus() != Status::STATUS_ENABLED) {
                $this->unsetRewardRules($trueloyalQuote);
                $this->messageManager->addError(__("Product that you are trying to add is not available."));
                return $resultJson->setData([
                    'success' => false
                ]);
            }
        }
        if ($rewardData["rule"] === "flexible_points_reward") {
            $quote = $this->helper->getQuote();
            $subTotal = $quote->getSubtotal();
            $conversion_rate = $rewardData['conversion_rate'];
            $redeemPoints = (int) $this->request->getPost('redeem_points');
            if ( $redeemPoints >= $rewardData['minimum_redemption_limit'] ) {
                $redeemPoints = min($subTotal*$conversion_rate, max($rewardData['minimum_redemption_limit'], min($redeemPoints, $rewardData['maximum_redemption_limit'])));
            }
            else {
                $this->unsetRewardRules($trueloyalQuote);
                $this->messageManager->addError(__("Please enter the correct number of points to be redeemed."));
                return $resultJson->setData([
                    'success' => false
                ]);
            }
            $rewardData["points_to_be_redeemed"] = $redeemPoints;
            $rewardRules[$redeemReward]['points_to_be_redeemed'] = $redeemPoints;
            $trueloyalQuote->setRewardRulesData($this->helper->jsonSerialize($rewardRules));
            $trueloyalQuote->save();
        }
        $response = $this->getApiResponse($rewardData);

        if ($response["success"] && !empty($response["result"]["data"])) {
            $responseData = $response["result"]["data"];
            if ($responseData["status"] === "pending") {
                $this->saveQuoteData($trueloyalQuote, $responseData, $redeemReward, $rewardData);
                $this->messageManager->addSuccess(__('You have redeemed %1 successfully.', $rewardData['reward_name']));
                return $resultJson->setData([
                    'success' => true
                ]);
            } else {
                $this->unsetRewardRules($trueloyalQuote);
                $this->messageManager->addError(
                    __("This reward rule can not be redeemed, try with another reward rule")
                );
                return $resultJson->setData([
                    'success' => false
                ]);
            }
        } else {
            $this->unsetRewardRules($trueloyalQuote);
            $this->messageManager->addError(__("This reward rule can not be redeemed, try with another reward rule"));
            return $resultJson->setData([
                'success' => false
            ]);
        }
    }

    /**
     * Delete reward relus from quote when getting error from response
     *
     * @param TrueLoyalQuote $trueloyalQuote
     */
    public function unsetRewardRules($trueloyalQuote)
    {
        try {
            $trueloyalQuote->setRewardRulesData('');
            $trueloyalQuote->save();
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }

    /**
     * Get Api Response
     *
     * @param mixed $rewardData
     * @return mixed
     */
    public function getApiResponse($rewardData)
    {
        $url = $this->helper->getLiveWebHookUrl() . "transactions/redeem";
        $paramsData = $this->getParamsData($rewardData);
        $params = $this->helper->jsonSerialize($paramsData);
        return $this->helper->request($url, $params, "post", "live_api");
    }

    /**
     * Get Params Data
     *
     * @param mixed $rewardData
     * @return array
     */
    public function getParamsData($rewardData)
    {
        $params = [
            "member_id" => $this->helper->getMemberIdentifierValue(),
            "reward_id" => $rewardData["reward_id"],
            "transaction_attributes" => [
                "reason" => "redeem",
                "tags" => [
                    "purchasenmade",
                    "redeemingpoints"
                ]
            ],
            "status" => "pending"
        ];
    
        if ($rewardData["rule"] === "flexible_points_reward" && isset($rewardData["points_to_be_redeemed"])) {
            $params["points_to_be_redeemed"] = (int)$rewardData["points_to_be_redeemed"];
        }
    
        return $params;
    }

    /**
     * Save Quote Data
     *
     * @param TrueLoyalQuote $trueloyalQuote
     * @param mixed $responseData
     * @param mixed $redeemReward
     * @param mixed $rewardData
     */
    public function saveQuoteData($trueloyalQuote, $responseData, $redeemReward, $rewardData)
    {
        try {
            if ($rewardData["rule"] === "product_redemption" && !empty($rewardData["product_id"])) {
                $this->addToCartProductWithNewPrice($rewardData);
            }
            $allRewardRules = $this->helper->json->unserialize($trueloyalQuote->getRewardRulesData());
            $allRewardRules[$responseData["reward_info"]["reward_id"]]["id"] = $responseData["id"];
            $encodedRule = $this->helper->json->serialize($allRewardRules);
            $trueloyalQuote->setRewardRulesData($encodedRule);
            $trueloyalQuote->setRedeemRewardDiscount($redeemReward);
            $trueloyalQuote->save();
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }

    /**
     * Add To Cart Product With New Price
     *
     * @param mixed $rewardData
     */
    public function addToCartProductWithNewPrice($rewardData)
    {
        try {
            $productId = $rewardData["product_id"];
            $additionalOptions[] = [
                'label' => __("Product Redemption"),
                'value' => $rewardData['reward_name']
            ];
            $params = [
                'form_key' => $this->formKey->getFormKey(),
                'product' => $productId,
                'qty' => 1
            ];
            $product = $this->product->load($productId);
            $product->setPrice(0);
            $product->addCustomOption('additional_options', $this->serializer->serialize($additionalOptions));
            $this->cart->addProduct($product, $params);
            $this->cart->save();
            /*Set free product to TrueLoyal quote item*/
            $quoteItemCollection = $this->cart->getItems();
            foreach ($quoteItemCollection as $item) {
                if($item->getProductId() == $productId && $item->getPrice() == 0) {
                    $trueloyalQuoteItem = $this->helper->getTrueLoyalQuoteItemByItemId($item->getId());
                    $trueloyalQuoteItem->setIsTrueLoyalFreeProduct(1)->setQuoteItemId($item->getId())->save();
                    break;
                }
            }
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }
}
