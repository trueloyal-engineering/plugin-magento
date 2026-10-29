<?php
/**
 * Copyright © TrueLoyal. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\ResourceModel\TrueLoyalQuoteItem;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalQuoteItem as TrueLoyalQuoteItemModel;
use TrueLoyal\LoyaltyRewards\Model\ResourceModel\TrueLoyalQuoteItem as TrueLoyalQuoteItemResourceModel;

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
            TrueLoyalQuoteItemModel::class,
            TrueLoyalQuoteItemResourceModel::class
        );
    }
}

