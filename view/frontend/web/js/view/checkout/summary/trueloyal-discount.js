define(
    [
        'jquery',
        'Magento_Checkout/js/view/summary/abstract-total',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/totals',
        'Magento_Catalog/js/price-utils'
    ],
    function ($,Component,quote,totals,priceUtils) {
        "use strict";
        return Component.extend({
            defaults: {
                template: 'TrueLoyal_LoyaltyRewards/checkout/summary/trueloyal-discount'
            },
            totals: quote.getTotals(),
            isDisplayedTrueLoyaldiscountTotal : function () {
                if(totals.getSegment('trueloyal_discount')) {
                    return true;
                }else {
                    return false;
                }
            },
            getTrueLoyaldiscountTotal : function () {
                if(totals.getSegment('trueloyal_discount')){
                    var price =  totals.getSegment('trueloyal_discount').value;
                    return this.getFormattedPrice(price);
                }
            },
            getTrueLoyaldiscountLabel : function () {
                if(totals.getSegment('trueloyal_discount')){
                    return totals.getSegment('trueloyal_discount').title;
                }
            }
        });
    }
);
