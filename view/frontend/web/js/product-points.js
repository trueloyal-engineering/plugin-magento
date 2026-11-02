/**
 * Product page: ask the TrueLoyal SDK for the points the product earns at the chosen quantity
 * (shown in the [data-zrl-product-points] element of catalog/reward_points.phtml)
 */
define([
    'jquery',
    'TrueLoyal_LoyaltyRewards/js/sdk'
], function ($, sdk) {
    'use strict';

    return function () {
        function requestPoints(qty) {
            var price = parseFloat($('.product-info-price .price').first().text().replace(/[^\d.-]/g, ''));

            /* global zrl_mi, potential_points */
            zrl_mi.get_potential_points_success_handler = function () {
                var element = document.getElementById('potential_product_points');

                if (element) {
                    element.innerText = potential_points;
                }
            };
            zrl_mi.get_potential_points([{
                'product_id': $('input[name="product"]').val(),
                'price': price,
                'quantity': qty,
                'category': ''
            }]);
        }

        sdk.done(function () {
            requestPoints(1);
            $('#qty').on('change', function () {
                requestPoints($(this).val());
            });
        });
    };
});
