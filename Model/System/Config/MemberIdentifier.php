<?php

namespace TrueLoyal\LoyaltyRewards\Model\System\Config;

use Magento\Framework\Data\OptionSourceInterface;

class MemberIdentifier implements OptionSourceInterface
{
    public const MEMBER_ID = 'member_id';
    public const MEMBER_EMAIL = 'member_email';

    /**
     * To Option Array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::MEMBER_EMAIL, 'label' => __('Member Email')],
            ['value' => self::MEMBER_ID, 'label' => __('Member ID')],
        ];
    }
}
