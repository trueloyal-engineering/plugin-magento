<?php
/**
 * Copyright © TrueLoyal. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model;

use Magento\Framework\Model\AbstractModel;
use TrueLoyal\LoyaltyRewards\Model\ResourceModel\TrueLoyalQuote as TrueLoyalQuoteResourceModel;

class TrueLoyalQuote extends AbstractModel
{

    /**
     * @inheritDoc
     */
    public function _construct()
    {
        $this->_init(TrueLoyalQuoteResourceModel::class);
    }
}

