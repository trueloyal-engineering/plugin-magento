var config = {
    map: {
        "*": {
            'Magento_Checkout/template/minicart/item/default.html': 'TrueLoyal_LoyaltyRewards/template/minicart/item/default.html',
            priceBox:'TrueLoyal_LoyaltyRewards/js/price-box-trueloyal',
            cartPoints: 'TrueLoyal_LoyaltyRewards/js/cart-points'
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/model/checkout-data-resolver': {
                'TrueLoyal_LoyaltyRewards/js/model/checkout-data-resolver': true
            }
        }
    }
};
