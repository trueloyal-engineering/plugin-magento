<?php
/**
 * Copyright © TrueLoyal. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\ResourceModel\TrueLoyalReview;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use TrueLoyal\LoyaltyRewards\Model\TrueLoyalReview as TrueLoyalReviewModel;
use TrueLoyal\LoyaltyRewards\Model\ResourceModel\TrueLoyalReview as TrueLoyalReviewResourceModel;

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
            TrueLoyalReviewModel::class,
            TrueLoyalReviewResourceModel::class
        );
    }
}

