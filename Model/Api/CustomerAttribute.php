<?php

namespace TrueLoyal\LoyaltyRewards\Model\Api;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Attribute;
use Magento\Eav\Model\Config;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Request;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class CustomerAttribute
{
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
     * @var Request
     */
    private $request;
    /**
     * @var Data
     */
    private $helper;

    /**
     * CustomerAttribute Constructor
     *
     * @param EavSetupFactory $eavSetupFactory
     * @param Config $eavConfig
     * @param Request $request
     * @param Attribute $attributeResource
     * @param Data $helper
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory,
        Config $eavConfig,
        Request $request,
        Attribute $attributeResource,
        Data $helper
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig = $eavConfig;
        $this->attributeResource = $attributeResource;
        $this->request = $request;
        $this->helper = $helper;
    }

    /**
     * Create Customer Attribute
     *
     * @return array
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function createCustomerAttribute()
    {
        $attributes = $this->request->getBodyParams();
        $requestedApiKey = $this->request->getHeader('api-key');
        $requestedPartnerId = $this->request->getHeader('partner-id');
        $response = [];
        if (!$this->helper->isModuleEnabled()) {
            $response[] = [
                        'status' => false,
                        'message' => __('TrueLoyal LoyaltyRewards module is not enabled.') . " " .
                        __('Enable module from: Stores → Configuration →TrueLoyal Loyalty Rewards → TrueLoyal Settings.')
                    ];
            return $response;
        /*Check header api-key and partner-id with configured auth key*/
        } elseif (!$this->helper->isValidateApiAuth($requestedApiKey, $requestedPartnerId)) {
            $response[] = [
                        'status' => false,
                        'message' => __('An invalid request data passed. Kindly check auth header data and try again.')
                    ];
            return $response;
        } elseif (isset($attributes['customer_attributes'])) {
            foreach ($attributes['customer_attributes'] as $customerAttribute) {
                try {
                    $eavSetup = $this->eavSetupFactory->create();
                    $eavSetup->addAttribute(
                        Customer::ENTITY,
                        $customerAttribute['attribute_code'],
                        [
                            'type' => 'varchar',
                            'label' => $customerAttribute['label'],
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
                    $attribute = $this->eavConfig->getAttribute(Customer::ENTITY, $customerAttribute['attribute_code']);
                    $attribute->setData('attribute_set_id', $attributeSetId);
                    $attribute->setData('attribute_group_id', $attributeGroupId);
                    $attribute->setData('used_in_forms', [
                        'adminhtml_customer'
                    ]);
                    $this->attributeResource->save($attribute);
                    /*Set attribute as TrueLoyal*/
                    $attributeId = $this->helper->getCustomerAttributeId($customerAttribute['attribute_code']);
                    $eavAttribute = $this->helper->getTrueLoyalAttributeByAttributeId($attributeId);
                    $eavAttribute->setAttributeId($attributeId)->setIsTrueLoyalAttribute(1)->save();
                    /*End*/
                    $response[] = [
                        'status' => true,
                        'message' => $attribute['frontend_label'] . " " . __('Attribute created successfully.')
                    ];
                } catch (\Exception $exception) {
                    throw new CouldNotSaveException(
                        __('Could not save %1', $customerAttribute['attribute_code']),
                        $exception
                    );
                } catch (\NoSuchEntityException $noSuchEntityException) {
                    throw new NoSuchEntityException(
                        __('Could not get %1 attribute.', $customerAttribute['attribute_code']),
                        $noSuchEntityException
                    );
                }
            }
        } else {
            $response[] = [
                        'status' => false,
                        'message' => __('Requested payload is not valid, please check and retry.')
                    ];
        }
        return $response;
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
