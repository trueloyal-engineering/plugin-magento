/**
 * Minicart: the redeemed gift (isCustomizedEnabledQtyBox = 0 in the cart section) can't have its quantity changed,
 * be edited or be removed; cancelling the reward on the cart page removes it.
 */
define([
    'jquery',
    'Magento_Customer/js/customer-data'
], function ($, customerData) {
    'use strict';

    function lockGifts() {
        (customerData.get('cart')().items || []).forEach(function (item) {
            var $qty;

            if (item.isCustomizedEnabledQtyBox !== 0) {
                return;
            }
            $qty = $('#cart-item-' + item.item_id + '-qty');
            $qty.prop('disabled', true);
            $qty.closest('.product-item').find('.update-cart-item, .product.actions').hide();
        });
    }

    return function (Minicart) {
        return Minicart.extend({
            initialize: function () {
                var content;

                this._super();
                customerData.get('cart').subscribe(function () {
                    setTimeout(lockGifts, 0);
                });
                content = document.querySelector('[data-block="minicart"]');
                if (content && window.MutationObserver) {
                    new MutationObserver(lockGifts).observe(content, {childList: true, subtree: true});
                }
                return this;
            }
        });
    };
});
