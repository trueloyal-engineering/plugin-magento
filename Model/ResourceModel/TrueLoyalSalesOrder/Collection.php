<?php
/**
 * Copyright © TrueLoyal. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\ResourceModel\TrueLoyalSalesOrder;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalSalesOrder as TrueLoyalSalesOrderModel;
use TrueLoyal\LoyaltyRewards\Model\ResourceModel\TrueLoyalSalesOrder as TrueLoyalSalesOrderResourceModel;
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
            TrueLoyalSalesOrderModel::class,
            TrueLoyalSalesOrderResourceModel::class
        );
    }
}

