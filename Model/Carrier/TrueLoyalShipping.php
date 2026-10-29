<?php

namespace TrueLoyal\LoyaltyRewards\Model\Carrier;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Psr\Log\LoggerInterface;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class TrueLoyalShipping extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'trueloyalrate';
    /**
     * @var bool
     */
    protected $_isFixed = true;
    /**
     * @var ResultFactory
     */
    protected $rateResultFactory;
    /**
     * @var MethodFactory
     */
    protected $rateMethodFactory;
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;
    /**
     * @var Data
     */
    private $helper;

    /**
     * TrueLoyalShipping constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param Data $helper
     * @param CheckoutSession $checkoutSession
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        Data $helper,
        CheckoutSession $checkoutSession,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->helper = $helper;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * Get Allowed Methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return ['trueloyalrate' => $this->getConfigData('name')];
    }

    /**
     * TrueLoyal free shipping
     *
     * @param RateRequest $request
     * @return bool|DataObject|Result|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function collectRates(RateRequest $request)
    {
        $quote = $this->checkoutSession->getQuote();
        $rewardData = $this->helper->getRewardRulesData($quote);
        $result = $this->rateResultFactory->create();
        if (!empty($rewardData) && $rewardData["rule"] == 'free_shipping') {
            $method = $this->rateMethodFactory->create();
            $title = $this->helper->getFreeShippingLabel() ?? $this->getConfigData('title');
            $amount = $this->getConfigData('price');
            $shippingPrice = $this->getFinalPriceWithHandlingFee($amount);
            $method->setCarrier('trueloyalrate');
            $method->setCarrierTitle($this->getConfigData('name'));
            $method->setMethod('trueloyalrate');
            $method->setMethodTitle($title);
            $method->setCost($amount);
            $method->setPrice($shippingPrice);
            $result->append($method);
            return $result;
        } else {
            $error = $this->_rateErrorFactory->create();
            $error->setCarrier('trueloyalrate');
            $result->append($error);
            return $result;
        }
    }
}
