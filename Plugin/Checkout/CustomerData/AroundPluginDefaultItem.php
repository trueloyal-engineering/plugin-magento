<?php

namespace TrueLoyal\LoyaltyRewards\Plugin\Checkout\CustomerData;

use Closure;
use Magento\Quote\Model\Quote\Item;
use TrueLoyal\LoyaltyRewards\Helper\Data;

class AroundPluginDefaultItem
{
    public const GROUP = 'grouped';
    /**
     * @var Data
     */
    private $helperData;

    /**
     * AroundPluginDefaultItem constructor
     *
     * @param Data $helperData
     */
    public function __construct(Data $helperData)
    {

        $this->helperData = $helperData;
    }

    /**
     * Around getItemData
     *
     * @param mixed $subject
     * @param Closure $proceed
     * @param Item $item
     * @return mixed
     */
    public function aroundGetItemData($subject, Closure $proceed, Item $item)
    {
        $data = $proceed($item);
        $data['isCustomizedEnabledQtyBox'] = $this->isQtyBoxEnabled($item);
        return $data;
    }

    /**
     * Return the QTY box visible status
     *
     * @param mixed $item
     * @return int
     */
    protected function isQtyBoxEnabled($item)
    {
        $productId = $this->helperData->getFreeProduct();
        if ($productId) {
            $trueloyalQuoteItem = $this->helperData->getTrueLoyalQuoteItemByItemId($item->getId());
            if ($trueloyalQuoteItem->getIsTrueLoyalFreeProduct() == 1) {
                return 0;
            }
        }
        return 1;
    }
}
