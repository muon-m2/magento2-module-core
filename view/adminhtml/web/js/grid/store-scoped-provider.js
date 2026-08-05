/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

/**
 * A grid data provider that carries the admin store switcher's scope into its data request.
 *
 * WHY THIS IS NEEDED. A UI listing does not render its rows with the page — it fetches them over
 * AJAX from `mui/index/render?namespace=…`, and that URL is built from the namespace and the grid's
 * own state only. Arbitrary parameters on the page URL, including the `store` the Magento store
 * switcher appends, are NOT forwarded. Server-side the request therefore looks unscoped, and a
 * store-scoped grid silently shows default values no matter which store view is selected.
 *
 * Appending `store` to the provider's params fixes that, and because params flow through
 * `onParamsChange` the scope also survives paging, sorting, filtering and search.
 *
 * DELIBERATELY A DISTINCT COMPONENT RATHER THAN A REQUIREJS MIXIN on Magento_Ui/js/grid/provider.
 * A mixin would attach to EVERY admin listing in the installation, including core ones, to serve two
 * grids. Listings that want this opt in by naming this component.
 */
define([
    'underscore',
    'Magento_Ui/js/grid/provider'
], function (_, Provider) {
    'use strict';

    /**
     * Read the store scope out of the current admin URL.
     *
     * Admin URLs are path-segment based (`.../item/index/store/2/key/…`), so this reads a segment
     * pair rather than a query string. Returns null when no scope is selected, which leaves the
     * request exactly as it was — that is what keeps default scope behaving as before.
     *
     * @returns {?String}
     */
    function currentStoreId() {
        var segments = window.location.pathname.split('/'),
            index = segments.indexOf('store');

        if (index === -1 || !segments[index + 1]) {
            return null;
        }

        return /^\d+$/.test(segments[index + 1]) ? segments[index + 1] : null;
    }

    return Provider.extend({
        /**
         * Add the store scope to every data request this provider makes.
         *
         * reload() is the single funnel — Provider::reload() passes `this.params` to the storage,
         * and paging, sorting, filtering and search all arrive here through onParamsChange. Adding
         * the scope at this point therefore survives all of them.
         *
         * A plain assignment rather than set(): `params` has a listener bound to onParamsChange, and
         * writing through set() here would re-enter reload().
         *
         * @param {Object} [options]
         * @returns {*}
         */
        reload: function (options) {
            var storeId = currentStoreId();

            if (storeId !== null) {
                this.params = _.extend({}, this.params, {
                    store: storeId
                });
            }

            return this._super(options);
        }
    });
});
