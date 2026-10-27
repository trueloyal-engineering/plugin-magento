define([
    'jquery'
], function ($) {
    'use strict';

    /**
     * Resolved by TrueLoyal_LoyaltyRewards/js/widget once the TrueLoyal SDK is loaded and initialized.
     * Code that calls zrl_mi waits on it: the SDK loads only after the member JWT arrives, and not at all
     * for visitors without a widget.
     */
    return $.Deferred();
});
