<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Plugin\Cart;

use Magento\Checkout\Block\Cart\Item\Renderer\Actions\Generic;
use TrueLoyal\LoyaltyRewards\Helper\Config;

/**
 * Cart page: no remove, edit or move-to-wishlist actions on the redeemed gift (cancelling the reward removes it)
 */
class HideGiftActions
{
    public function __construct(private readonly Config $config)
    {
    }

    public function afterToHtml(Generic $subject, string $html): string
    {
        $item = $subject->getItem();
        if ($item && $item->getId()
            && $this->config->getTrueLoyalQuoteItemByItemId($item->getId())->getData('is_trueloyal_free_product')
        ) {
            return '';
        }
        return $html;
    }
}
