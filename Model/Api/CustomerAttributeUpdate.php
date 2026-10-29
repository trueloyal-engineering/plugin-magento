<?php

namespace TrueLoyal\LoyaltyRewards\Model\Api;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Eav\Model\Config;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Store\Model\StoreManagerInterface;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class CustomerAttributeUpdate
{
    /**
     * @var CustomerRepository
     */
    protected $customerRepository;
    /**
     * @var Request
     */
    private $request;
    /**
     * @var Customer
     */
    private $customer;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var Config
     */
    private $eavConfig;
    /**
     * @var Data
     */
    private $helper;

    /**
     * CustomerAttributeUpdate constructor.
     *
     * @param Context $context
     * @param Request $request
     * @param Config $eavConfig
     * @param StoreManagerInterface $storeManager
     * @param CustomerFactory $customer
     * @param Data $helper
     */
    public function __construct(
        Context $context,
        Request $request,
        Config $eavConfig,
        StoreManagerInterface $storeManager,
        CustomerFactory $customer,
        Data $helper
    ) {
        $this->request = $request;
        $this->eavConfig = $eavConfig;
        $this->customer = $customer;
        $this->storeManager = $storeManager;
        $this->helper = $helper;
    }

    /**
     * Update Customer AttributeValue
     *
     * @return array
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function updateCustomerAttributeValue()
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
            foreach ($attributes['customer_attributes'] as $attribute) {
                $attributeValues = $this->eavConfig->getAttribute(Customer::ENTITY, $attribute['attribute_code']);
                $websiteID = $this->storeManager->getStore()->getWebsiteId();
                $customer = $this->customer->create()->setWebsiteId($websiteID)->loadByEmail($attribute['customer_email']);
                $eavAttribute = $this->helper->getTrueLoyalAttributeByAttributeId($attributeValues->getAttributeId());
                if ($eavAttribute->getIsTrueLoyalAttribute() && $customer->getData()) {
                    $customerData = $customer->getDataModel();
                    $customerData->setCustomAttribute($attribute['attribute_code'], $attribute['value']);
                    $customer->updateData($customerData);
                    try {
                        $customer->save();
                    } catch (\Exception $e) {
                        throw new CouldNotSaveException(
                            __('Could not save data: %1', $e->getMessage()),
                            $e
                        );
                    }
                    $response[] = [
                        'status' => true,
                        'message' =>  $attribute['attribute_code'] . " ". __('Attribute value updated successfully.')
                    ];
                } else {
                    $response[] = [
                        'status' => false,
                        'message' =>  $attribute['attribute_code'] . " " . __('Attribute value can not be updated.')
                    ];
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
}
