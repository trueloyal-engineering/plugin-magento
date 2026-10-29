<?php
/**
 * Copyright © TrueLoyal. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\ResourceModel\TrueLoyalEavAttribute;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalEavAttribute as TrueLoyalEavAttributeModel;
use TrueLoyal\LoyaltyRewards\Model\ResourceModel\TrueLoyalEavAttribute as TrueLoyalEavAttributeResourceModel;

class Collection extends AbstractCollection
{

    /**
     * @inheritDoc
     */
    protected $_idFieldName = 'id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(
            TrueLoyalEavAttributeModel::class,
            TrueLoyalEavAttributeResourceModel::class
        );
    }
}

