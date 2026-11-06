<?php
namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class CustomerSaveAfter implements ObserverInterface
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var AddressRepositoryInterface
     */
    private $addressRepository;
    /**
     * @var Http
     */
    private $request;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * Customer Register constructor.
     *
     * @param Data $helper
     * @param Http $request
     * @param Registry $registry
     * @param CustomerRepositoryInterface $customerRepository
     * @param AddressRepositoryInterface $addressRepository
     */
    public function __construct(
        Data $helper,
        Http $request,
        Registry $registry,
        CustomerRepositoryInterface $customerRepository,
        AddressRepositoryInterface $addressRepository
    ) {
        $this->helper = $helper;
        $this->request = $request;
        $this->registry = $registry;
        $this->customerRepository = $customerRepository;
        $this->addressRepository = $addressRepository;
    }

    /**
     * Create customer event to trueloyal
     *
     * @param Observer $observer
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $customerAddressData = [];
        $addressId ='';
        $activity_id = "";
        $customerId = "";
        $customerSave = $this->registry->registry('customer_save_event');
        $customerSaveAddress = $this->registry->registry('customer_address_save_event');
        if ($observer->getEvent()->getName() == "customer_save_commit_after" && !$customerSaveAddress) {
            /* Sign-up saves the customer twice in one request: handle the first save only */
            if ($customerSave || !$observer->getCustomer()) {
                return;
            }
            $customerData = $observer->getCustomer()->getData();
            if (isset($customerData['id'])) {
                if ($this->request->getActionName() == 'editPost' ||
                    (isset($this->request->getParam('customer')['entity_id']) && $this->request->getParam('customer')['entity_id'])) {
                    $customerId = $customerData['id'];
                    $activity_id = "customer_update";
                }
            } else {
                $customerId = $customerData['entity_id'];
                $activity_id = "customer_create";
            }
            if($this->registry->registry('customer_address_save_event')) {
                $this->registry->unregister('customer_address_save_event');
            }
            $this->registry->register('customer_save_event', '1');
        } elseif ($observer->getEvent()->getName() == "customer_address_save_commit_after" && !$customerSave) {
            $customerAddressData = $observer->getCustomerAddress()->getData();
            foreach ($customerAddressData as $key => $customerAdd) {
                if (empty($customerAdd)) {
                    unset($customerAddressData[$key]);
                }
            }
            unset($customerAddressData["entity_id"], $customerAddressData["parent_id"]);
            $customerId = $customerAddressData["customer_id"];
            $addressId = isset($customerAddressData["id"]) ? $customerAddressData["id"] : null;
            $activity_id = "customer_update";
            if($this->registry->registry('customer_address_save_event')) {
                $this->registry->unregister('customer_address_save_event');
            }
            $this->registry->register('customer_address_save_event', '1');
        } elseif ($observer->getEvent()->getName() == "customer_address_delete_commit_after") {
            $customerData = $observer->getCustomerAddress()->getData();
            $customerId = $customerData['parent_id'];
            $activity_id = "customer_update";
        }
        if ($activity_id === "") {
            return;
        }
        /*Settings of the customer's website: in the admin and the API the current store is another one*/
        $this->helper->runInStoreScope(
            $this->getScopeStoreId($observer, $customerId),
            function () use ($observer, $customerId, $addressId, $customerAddressData, $activity_id): void {
                if (in_array($activity_id, $this->helper->getRewardEvents(), true)) {
                    $this->prepareParams($observer, $customerId, $addressId, $customerAddressData, $activity_id);
                }
            }
        );
    }

    /**
     * Store whose settings apply to the saved customer (see Data::getCustomerScopeStoreId())
     *
     * @param Observer $observer
     * @param int|mixed $customerId
     * @return int|null
     */
    private function getScopeStoreId($observer, $customerId): ?int
    {
        try {
            $customer = $observer->getEvent()->getName() == "customer_save_commit_after"
                ? $observer->getCustomer()
                : $this->customerRepository->getById($customerId);
            return $this->helper->getCustomerScopeStoreId($customer);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Prepare payload and Send customer create-update request TrueLoyal
     *
     * @param Observer $observer
     * @param int|mixed $customerId
     * @param int|mixed $addressId
     * @param array $customerAddressData
     * @param string $activity_id
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function prepareParams($observer, $customerId, $addressId, $customerAddressData, $activity_id)
    {
        $customer = $this->customerRepository->getById($customerId);
        $customerData = $customer->__toArray();
        if ($observer->getEvent()->getName() == "customer_address_save_commit_after") {
            $addresses = $customerData["addresses"];
            unset($customerData["addresses"]);
            $addressIds = [];
            foreach ($addresses as $address) {
                $addressIds[] = $address["id"];
                $addressData = $this->addressRepository->getById($address["id"]);
                $customerData["addresses"][] = $addressData->__toArray();
            }
            if (!in_array($addressId, $addressIds, true)) {
                $customerData["addresses"][] = $customerAddressData;
            }
        }
        $params = [
            "member_id" => $this->helper->getMemberIdentifierValueById($customerId, $customer->getEmail()),
            "activity_id" => $activity_id,
            "data" => $customerData
        ];
        $url = $this->helper->getWebHookUrl();
        $params = $this->helper->json->serialize($params);
        $this->helper->request($url, $params, "post");
    }
}
