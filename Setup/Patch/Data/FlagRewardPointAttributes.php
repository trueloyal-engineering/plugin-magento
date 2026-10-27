<?php

namespace TrueLoyal\LoyaltyRewards\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use TrueLoyal\LoyaltyRewards\Helper\Data;

/**
 * RewardPoint wrote the TrueLoyal flag through a broken magic setter, so installs that already ran it
 * have loyalty_tier and available_points unflagged and the inbound API refuses to update them.
 */
class FlagRewardPointAttributes implements DataPatchInterface
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * FlagRewardPointAttributes Constructor
     *
     * @param Data $helper
     */
    public function __construct(
        Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Get Dependencies
     *
     * @return array|string[]
     */
    public static function getDependencies()
    {
        return [RewardPoint::class];
    }

    /**
     * Flag loyalty_tier and available_points as TrueLoyal attributes
     */
    public function apply()
    {
        foreach (['loyalty_tier', 'available_points'] as $attributeCode) {
            $attributeId = $this->helper->getCustomerAttributeId($attributeCode);
            if (!$attributeId) {
                continue;
            }
            $eavAttribute = $this->helper->getTrueLoyalAttributeByAttributeId($attributeId);
            $eavAttribute->setAttributeId($attributeId)->setData('is_trueloyal_attribute', 1)->save();
        }
    }

    /**
     * Get Aliases
     *
     * @return array|string[]
     */
    public function getAliases()
    {
        return [];
    }
}
