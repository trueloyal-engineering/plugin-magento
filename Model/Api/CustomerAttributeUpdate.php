<?php

namespace TrueLoyal\LoyaltyRewards\Model\Api;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Eav\Model\Config;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Request;
use TrueLoyal\LoyaltyRewards\Helper\Data;
use TrueLoyal\LoyaltyRewards\Model\Event\Customer\CustomerSync;

class CustomerAttributeUpdate
{
    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * CustomerAttributeUpdate constructor.
     *
     * @param Context $context
     * @param Request $request
     * @param Config $eavConfig
     * @param CustomerFactory $customer
     * @param Data $helper
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param CustomerSync $customerSync
     */
    public function __construct(
        Context $context,
        private readonly Request $request,
        private readonly Config $eavConfig,
        private readonly CustomerFactory $customer,
        private readonly Data $helper,
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly CustomerSync $customerSync
    ) {
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
                $eavAttribute = $this->helper->getTrueLoyalAttributeByAttributeId($attributeValues->getAttributeId());
                /* A member is one email: update the customer on every website it has an account on */
                $customerIds = $this->customerCollectionFactory->create()
                    ->addAttributeToFilter('email', (string)$attribute['customer_email'])
                    ->getAllIds();
                if ($eavAttribute->getData('is_trueloyal_attribute') && $customerIds) {
                    foreach ($customerIds as $customerId) {
                        $customer = $this->customer->create()->load($customerId);
                        $customerData = $customer->getDataModel();
                        $customerData->setCustomAttribute($attribute['attribute_code'], $attribute['value']);
                        $customer->updateData($customerData);
                        try {
                            /* TrueLoyal sent this value: don't send it back as customer_update */
                            $this->customerSync->withoutSync(fn () => $customer->save());
                        } catch (\Exception $e) {
                            throw new CouldNotSaveException(
                                __('Could not save data: %1', $e->getMessage()),
                                $e
                            );
                        }
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
