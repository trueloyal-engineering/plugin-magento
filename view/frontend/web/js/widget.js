define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'TrueLoyal_LoyaltyRewards/js/sdk',
    'mage/cookies'
], function ($, customerData, sdk) {
    'use strict';

    // Reload the token when it has less than this many seconds left
    var REFRESH_MARGIN = 300,
        // Set by the server on every customer or address save (Observer\Customer\ProfileChangedObserver)
        PROFILE_COOKIE = 'trueloyal_profile';

    /**
     * Init the TrueLoyal widget with the member JWT from the "trueloyal" customer-data section.
     * The token is private, so it never comes from the (full-page-cached) HTML.
     */
    return function (config) {
        var section = customerData.get('trueloyal'),
            initialized = false;

        /**
         * @param {Object} data
         */
        function init(data) {
            if (initialized || !data || !data.jwt_token) {
                return;
            }
            initialized = true;
            require(['trueloyal'], function () {
                _zrl.push(['init', {
                    'partner_id': config.partnerId,
                    'jwt_token': data.jwt_token,
                    'version': 'v2',
                    'server': config.server
                }]);
                sdk.resolve();
            });
        }

        customerData.getInitCustomerData().done(function () {
            var data = section(),
                // customer-data is already reloading it (e.g. after login or a profile change): wait for that
                isReloading = customerData.getExpiredSectionNames().indexOf('trueloyal') !== -1,
                isFresh = data && data['expires_at'] && data['expires_at'] - REFRESH_MARGIN > Date.now() / 1000,
                // The token was built before the last profile change, so it carries the old name or address
                isOutdated = data && ($.mage.cookies.get(PROFILE_COOKIE) || '') !== (data['profile_version'] || '');

            if (isFresh && !isReloading && !isOutdated) {
                init(data);

                return;
            }
            section.subscribe(init);

            if (!isReloading) {
                customerData.reload(['trueloyal'], false);
            }
        });
    };
});
