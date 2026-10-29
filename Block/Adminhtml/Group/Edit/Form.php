<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace TrueLoyal\LoyaltyRewards\Block\Adminhtml\Group\Edit;

use Magento\Customer\Controller\RegistryConstants;

/**
 * Adminhtml customer groups edit form
 */
class Form extends \Magento\Customer\Block\Adminhtml\Group\Edit\Form
{
    /**
     * Prepare form for render
     *
     * @return void
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        /** @var \Magento\Framework\Data\Form $form */
        $form = $this->_formFactory->create();

        $groupId = $this->_coreRegistry->registry(RegistryConstants::CURRENT_GROUP_ID);
        /** @var \Magento\Customer\Api\Data\GroupInterface $customerGroup */
        $customerGroupExcludedWebsites = [];
        if ($groupId === null) {
            $customerGroup = $this->groupDataFactory->create();
            $defaultCustomerTaxClass = $this->_taxHelper->getDefaultCustomerTaxClass();
        } else {
            $customerGroup = $this->_groupRepository->getById($groupId);
            $defaultCustomerTaxClass = $customerGroup->getTaxClassId();
            $customerGroupExcludedWebsites = $this->groupExcludedWebsiteRepository->getCustomerGroupExcludedWebsites(
                $groupId
            );
        }

        $fieldset = $form->addFieldset('base_fieldset', ['legend' => __('Group Information')]);

        $validateClass = sprintf(
            'required-entry validate-length maximum-length-%d',
            \Magento\Customer\Model\GroupManagement::GROUP_CODE_MAX_LENGTH
        );
        $name = $fieldset->addField(
            'customer_group_code',
            'text',
            [
                'name' => 'code',
                'label' => __('Group Name'),
                'title' => __('Group Name'),
                'note' => __(
                    'Maximum length must be less then %1 characters.',
                    \Magento\Customer\Model\GroupManagement::GROUP_CODE_MAX_LENGTH
                ),
                'class' => $validateClass,
                'required' => true
            ]
        );

        if ($customerGroup->getId() == 0 && $customerGroup->getCode()) {
            $name->setDisabled(true);
        }

        $fieldset->addField(
            'tax_class_id',
            'select',
            [
                'name' => 'tax_class',
                'label' => __('Tax Class'),
                'title' => __('Tax Class'),
                'class' => 'required-entry',
                'required' => true,
                'values' => $this->_taxCustomer->toOptionArray(),
            ]
        );
        $fieldset->addField(
            'CustomField',
            'text',
            [
                'name' => 'fieldname',
                'label' => __('CustomField'),
                'title' => __('CustomField'),
                'class' => 'validate-number'


            ]
        );
        $fieldset->addField(
            'customer_group_excluded_website_ids',
            'multiselect',
            [
                'name' => 'customer_group_excluded_websites',
                'label' => __('Excluded Website(s)'),
                'title' => __('Excluded Website(s)'),
                'required' => false,
                'can_be_empty' => true,
                'values' => $this->systemStore->getWebsiteValuesForForm(),
                'note' => __('Select websites you want to exclude from this customer group.')
            ]
        );

        if ($customerGroup->getId() !== null) {
            // If edit add id
            $form->addField('id', 'hidden', ['name' => 'id', 'value' => $customerGroup->getId()]);
        }

        if ($this->_backendSession->getCustomerGroupData()) {
            $form->addValues($this->_backendSession->getCustomerGroupData());
            $this->_backendSession->setCustomerGroupData(null);
        } else {
            // TODO: need to figure out how the DATA can work with forms
            $form->addValues(
                [
                    'id' => $customerGroup->getId(),
                    'customer_group_code' => $customerGroup->getCode(),
                    'tax_class_id' => $defaultCustomerTaxClass,
                    'customer_group_excluded_website_ids' => $customerGroupExcludedWebsites
                ]
            );
        }

        $form->setUseContainer(true);
        $form->setId('edit_form');
        $form->setAction($this->getUrl('customer/*/save'));
        $form->setMethod('post');
        $this->setForm($form);
    }
}