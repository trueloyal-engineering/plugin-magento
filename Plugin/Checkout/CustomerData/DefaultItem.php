<?php

namespace TrueLoyal\LoyaltyRewards\Plugin\Checkout\CustomerData;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Helper\Product\ConfigurationPool;
use Magento\Catalog\Model\Product\Configuration\Item\ItemResolverInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use TrueLoyal\LoyaltyRewards\Helper\Data;
use Magento\Checkout\CustomerData\DefaultItem as MainDefaultItem;
use Magento\Msrp\Helper\Data as MsrpData;
use Magento\Checkout\Helper\Data as CheckoutData;

class DefaultItem extends MainDefaultItem
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * DefaultItem constructor.
     *
     * @param Image $imageHelper
     * @param MsrpData $msrpHelper
     * @param Data $helper
     * @param UrlInterface $urlBuilder
     * @param ConfigurationPool $configurationPool
     * @param CheckoutData $checkoutHelper
     * @param Escaper|null $escaper
     * @param ItemResolverInterface|null $itemResolver
     */
    public function __construct(
        Image $imageHelper,
        MsrpData $msrpHelper,
        Data $helper,
        UrlInterface $urlBuilder,
        ConfigurationPool $configurationPool,
        CheckoutData $checkoutHelper,
        Escaper $escaper = null,
        ItemResolverInterface $itemResolver = null
    ) {

        $this->helper = $helper;
        parent::__construct(
            $imageHelper,
            $msrpHelper,
            $urlBuilder,
            $configurationPool,
            $checkoutHelper,
            $escaper,
            $itemResolver
        );
    }

    /**
     * DoGetItemData
     *
     * @return array
     */
    protected function doGetItemData()
    {
        $result = parent::doGetItemData();
        $result['isCustomizedEnabledQtyBox'] = $this->isQtyBoxEnabled($this->item);
        return $result;
    }

    /**
     * IsQtyBoxEnable
     *
     * @param mixed $item
     * @return int
     */
    protected function isQtyBoxEnabled($item)
    {
        $productId = $this->helper->getFreeProduct();
        if ($productId) {
            if ($item->getIsTrueLoyalFreeProduct() == 1) {
                return 0;
            }
        }
        return 1;
    }
}
