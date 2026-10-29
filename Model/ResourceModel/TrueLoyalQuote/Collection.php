<?php
/**
 * Copyright © TrueLoyal. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\ResourceModel\TrueLoyalQuote;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalQuote as TrueLoyalQuoteModel;
use TrueLoyal\LoyaltyRewards\Model\ResourceModel\TrueLoyalQuote as TrueLoyalQuoteResourceModel;

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
            TrueLoyalQuoteModel::class,
            TrueLoyalQuoteResourceModel::class
        );
    }
}

