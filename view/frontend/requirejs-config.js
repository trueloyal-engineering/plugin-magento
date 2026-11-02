var config = {
    map: {
        '*': {
            cartPoints: 'TrueLoyal_LoyaltyRewards/js/cart-points',
            trueloyal: 'https://cdn.trueloyal.com/js/all.js'
        }
    },
    config: {
        mixins: {
            'Magento_Catalog/js/price-box': {
                'TrueLoyal_LoyaltyRewards/js/price-box-mixin': true
            },
            'Magento_Checkout/js/view/minicart': {
                'TrueLoyal_LoyaltyRewards/js/view/minicart-mixin': true
            },
            'Magento_Checkout/js/model/checkout-data-resolver': {
                'TrueLoyal_LoyaltyRewards/js/model/checkout-data-resolver': true
            }
        }
    }
};
