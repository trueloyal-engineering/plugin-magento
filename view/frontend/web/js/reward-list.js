/**
 * Cart page rewards: apply a reward (asking how many points for flexible rewards) or cancel the applied one.
 * Both post to loyaltyRewards/loyaltyrewards/applyPoint and reload the page, since the reward changes cart lines
 * and totals.
 */
define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'Magento_Catalog/js/price-utils',
    'mage/cookies'
], function ($, customerData, priceUtils) {
    'use strict';

    return function (config, element) {
        var $root = $(element),
            $selector = $root.find('#redeem-reward'),
            $input = $root.find('#redeemPointsInput'),
            $modal = $root.find('#modalOverlay, #flexiblePointsModal');

        function formatPrice(amount) {
            return priceUtils.formatPrice(amount, (window.checkoutConfig || {}).priceFormat);
        }

        function subtotal() {
            return ((window.checkoutConfig || {}).totalsData || {}).subtotal || 0;
        }

        function selectedOption() {
            var $option = $selector.find('option:selected');

            return {
                rule: $option.data('rule'),
                conversionRate: parseFloat($option.data('conversion-rate')) || 0,
                min: parseInt($option.data('min-redemption'), 10) || 0,
                max: parseInt($option.data('max-redemption'), 10) || 0
            };
        }

        /* Points that count: within the reward's limits and no more than the subtotal is worth */
        function usablePoints(points, option) {
            return Math.min(subtotal() * option.conversionRate, Math.max(option.min, Math.min(points, option.max)));
        }

        function post(data) {
            $('body').trigger('processStart');
            $.ajax({
                url: config.url,
                type: 'POST',
                dataType: 'json',
                data: data,
                global: false,
                showLoader: false
            }).always(function () {
                // The reward adds or removes cart lines and changes totals: refresh the minicart too
                customerData.invalidate(['cart']);
                window.location.reload();
            });
        }

        function openFlexibleModal(option) {
            var points = usablePoints(config.points, option),
                discount = option.conversionRate ? points / option.conversionRate : 0;

            $input.attr({min: option.min, max: option.max}).val(points);
            $root.find('#discountAmount, #maxdiscountAmount').text(formatPrice(discount));
            $root.find('#minRedemption').text(option.min);
            $root.find('#maxRedemption').text(option.max);
            $modal.show();
            $('body').addClass('no-scroll');
        }

        function closeFlexibleModal() {
            $modal.hide();
            $('body').removeClass('no-scroll');
            $selector.val('').css('border-color', '#726d6d');
        }

        function disable() {
            $selector.css('border-color', '#726d6d').prop('disabled', true);
            $root.find('#redeem').prop('disabled', true);
        }

        if (!config.applied && $.mage.cookies.get('trueloyal_shipping_selected')) {
            $.mage.cookies.clear('trueloyal_shipping_selected');
        }

        $root.on('click', '#redeem', function (event) {
            var rewardId = $selector.val(),
                option = selectedOption();

            event.preventDefault();
            if (!rewardId) {
                $selector.css('border-color', 'red');
                return;
            }
            if (option.rule === 'flexible_points_reward') {
                openFlexibleModal(option);
                return;
            }
            disable();
            post({'redeem_reward': rewardId});
        });

        $root.on('click', '.apply-btn', function () {
            var rewardId = $selector.val(),
                points = parseInt($input.val(), 10) || 0;

            disable();
            post({'redeem_reward': rewardId, 'redeem_points': points});
        });

        $root.on('click', '.cancel-btn, .close-btn', closeFlexibleModal);

        $root.on('click', '#cancel-redeem', function (event) {
            event.preventDefault();
            $(this).prop('disabled', true);
            post({'redeem_reward': 'cancel'});
        });

        $root.on('input', '#redeemPointsInput', function () {
            var option = selectedOption(),
                points = parseInt($(this).val(), 10) || 0,
                usable = points < option.min ? 0 : usablePoints(points, option);

            $root.find('#discountAmount').text(formatPrice(option.conversionRate ? usable / option.conversionRate : 0));
        });
    };
});
