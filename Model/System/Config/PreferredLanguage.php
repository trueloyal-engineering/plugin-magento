<?php

namespace TrueLoyal\LoyaltyRewards\Model\System\Config;

use Magento\Framework\Data\OptionSourceInterface;

class PreferredLanguage implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '',                      'label' => __(' ')],
            ['value' => 'english',               'label' => __('English')],
            ['value' => 'arabic',                'label' => __('Arabic')],
            ['value' => 'french',                'label' => __('French')],
            ['value' => 'german',                'label' => __('German')],
            ['value' => 'italian',               'label' => __('Italian')],
            ['value' => 'spanish',               'label' => __('Spanish')],
            ['value' => 'custom language one',   'label' => __('Custom Language One')],
            ['value' => 'custom language two',   'label' => __('Custom Language Two')],
            ['value' => 'custom language three', 'label' => __('Custom Language Three')],
            ['value' => 'custom language four',  'label' => __('Custom Language Four')],
            ['value' => 'custom language five',  'label' => __('Custom Language Five')],
            ['value' => 'custom language six',  'label' => __('Custom Language Six')],
            ['value' => 'custom language seven',  'label' => __('Custom Language Seven')],
            ['value' => 'custom language eight',  'label' => __('Custom Language Eight')],
            ['value' => 'custom language nine',  'label' => __('Custom Language Nine')],
            ['value' => 'custom language ten',  'label' => __('Custom Language Ten')],
            ['value' => 'custom language eleven',  'label' => __('Custom Language Eleven')],
            ['value' => 'custom language twelve',  'label' => __('Custom Language Twelve')],
            ['value' => 'custom language thirteen',  'label' => __('Custom Language Thirteen')],
            ['value' => 'custom language fourteen',  'label' => __('Custom Language Fourteen')],
        ];
    }
}
