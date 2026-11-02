/**
 * After core's priceBox recalculates the price (options, quantity, tier prices), tell the TrueLoyal SDK the new
 * final price so the "earn N points" text on the product page follows it.
 */
define([
    'jquery',
    'TrueLoyal_LoyaltyRewards/js/sdk'
], function ($, sdk) {
    'use strict';

    return function (priceBox) {
        $.widget('mage.priceBox', priceBox, {
            reloadPrice: function () {
                var prices,
                    productId;

                this._super();
                prices = this.cache.displayPrices;
                productId = this.options.priceConfig && this.options.priceConfig.productId;
                if (!prices || !prices.finalPrice) {
                    return;
                }
                window.customFinalPrice = prices.finalPrice.amount;
                sdk.done(function () {
                    /* global zrl_mi */
                    zrl_mi.price_identifier = function () {
                        var product = {};

                        if (window.customFinalPrice) {
                            product.price = window.customFinalPrice;
                        }
                        if (productId) {
                            product.product_id = productId;
                        }
                        return product;
                    };
                    zrl_mi.price_identifier();
                    zrl_mi.replace_product_page_potential();
                });
            }
        });

        return $.mage.priceBox;
    };
});
