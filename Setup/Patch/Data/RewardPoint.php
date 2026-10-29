<?php

namespace TrueLoyal\LoyaltyRewards\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Attribute;
use Magento\Eav\Model\Config;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class RewardPoint implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;
    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;
    /**
     * @var Config
     */
    private $eavConfig;
    /**
     * @var Attribute
     */
    private $attributeResource;
    /**
     * @var Data
     */
    private $helper;

    /**
     * RewardPoint Constructor
     *
     * @param EavSetupFactory $eavSetupFactory
     * @param Config $eavConfig
     * @param Attribute $attributeResource
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param Data $helper
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory,
        Config $eavConfig,
        Attribute $attributeResource,
        ModuleDataSetupInterface $moduleDataSetup,
        Data $helper
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig = $eavConfig;
        $this->attributeResource = $attributeResource;
        $this->moduleDataSetup = $moduleDataSetup;
        $this->helper = $helper;
    }

    /**
     * Get Dependencies
     *
     * @return array|string[]
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * Create loyalty_tier and available_points customer attributes
     *
     * @throws AlreadyExistsException
     * @throws LocalizedException
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $customAttributes = [
            'loyalty_tier' => "Loyalty Tier",
            'available_points' => "Available Points"
        ];
        $eavSetup = $this->eavSetupFactory->create();
        foreach ($customAttributes as $attributeCode => $attributeLabel) {
            $eavSetup->addAttribute(
                Customer::ENTITY,
                $attributeCode,
                [
                'type' => 'varchar',
                'label' => $attributeLabel,
                'input' => 'text',
                'required' => 0,
                'visible' => 1,
                'user_defined' => 1,
                'sort_order' => 999,
                'position' => 999,
                'system' => 0
                ]
            );
            $attributeSetId = $eavSetup->getDefaultAttributeSetId(Customer::ENTITY);
            $attributeGroupId = $eavSetup->getDefaultAttributeGroupId(Customer::ENTITY);
            $attribute = $this->eavConfig->getAttribute(Customer::ENTITY, $attributeCode);
            $attribute->setData('attribute_set_id', $attributeSetId);
            $attribute->setData('attribute_group_id', $attributeGroupId);
            $attribute->setData('used_in_forms', [
                'adminhtml_customer'
            ]);
            $this->attributeResource->save($attribute);
            /*Set attribute as TrueLoyal*/
            $attributeId = $this->helper->getCustomerAttributeId($attributeCode);
            $eavAttribute = $this->helper->getTrueLoyalAttributeByAttributeId($attributeId);
            $eavAttribute->setAttributeId($attributeId)->setIsTrueLoyalAttribute(1)->save();
        }
        $this->moduleDataSetup->getConnection()->endSetup();
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
