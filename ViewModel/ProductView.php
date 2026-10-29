<?php
namespace TrueLoyal\LoyaltyRewards\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class ProductView implements ArgumentInterface
{
    /**
     * @var Data
     */
    public $helper;

    /**
     * ProductView ViewModel constructor.
     *
     * @param Data $helper
     */
    public function __construct(
        Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Get reward label
     *
     * @return string
     */
    public function getRewardLabel()
    {
        return $this->helper->getRewardLabelAtProductPage();
    }

    /**
     * Check Reward Point can show at PDP or not
     *
     * @return bool
     */
    public function isPdpPointEnabled()
    {
        return $this->helper->isRewardPointAtPdpEnabled();
    }

    /**
     * Check Reward Point can show at Cart Page or not
     *
     * @return bool
     */
    public function isPdpAtCartEnabled()
    {
        return $this->helper->isPDPAtCartEnabled();
    }

    /**
     * Get reward label for Cart Page
     *
     * @return string
     */
    public function getRewardLabelAtCartPage()
    {
        return $this->helper->getPDPLabelAtCartPage();
    }
}
