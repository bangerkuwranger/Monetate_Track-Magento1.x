<?php
/**
 * Monetate
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to support@monetate.com so we can send you a copy immediately.
 *
 * @category Monetate
 * @package Monetate_Track
 * @copyright Copyright (c) 2014 Monetate, Inc. All rights reserved. (http://www.monetate.com)
 * @license  http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Block with Monetate tag initialization functionality
 */
class Monetate_Track_Block_Init extends Monetate_Track_Block_Abstract
{
    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _getMonetateHtml()
    {
        $html = '
<!-- Begin Monetate ExpressTag - Init -->
<script type="text/javascript">
//<![CDATA[
    window.monetateQ = window.monetateQ || [];
    window.monetateData = window.monetateData || {};
    window.monetateData.pageType = "'.$this->_getPageType().'";
//]]>
</script>
<!-- End Monetate ExpressTag -->
';
        return $html;
    }

    /**
     * Get page identifier
     *
     * @return string
     */
    protected function _getPageType()
    {
        $pageIdentifier = $this->getAction()->getFullActionName();
        switch ($pageIdentifier) {
            /**
             * Home Page / Catalog Pages
             */
            case 'cms_index_index':
                return 'main';
            case 'catalog_category_view':
                $category = Mage::registry('current_category');
                if ($category && $category->getId() && $category->getDisplayMode() == 'PAGE') {
                    return 'category';
                }
                return 'index';
            case 'catalog_product_view':
                return 'product';
            case 'catalogsearch_result_index':
            case 'catalogsearch_advanced_result':
                return 'search';

            /**
             * Account Pages
             */
            case 'customer_account_login':
                return 'login';
            case 'customer_account_create':
                return 'signup';
            case 'customer_account_index':
                return 'account';
            case 'sales_order_view':
                return 'orderstatus';
            case 'wishlist_index_index':
            case 'wishlist_index_share':
                return 'wishlist';

            /**
             * Cart/Checkout
             */
            case 'checkout_cart_index':
                return 'cart';
            case 'checkout_onepage_index':
                if ($this->helper('customer')->isLoggedIn()) {
                    return 'billing';
                }
                return 'checkoutLogin';
            case 'checkout_onepage_success':
                return 'purchase';
            case 'checkout_multishipping_login':
                return 'checkoutLogin';
            case 'checkout_multishipping_register':
                return 'signup';
            case 'checkout_multishipping_addresses':
            case 'checkout_multishipping_address_newShipping':
            case 'checkout_multishipping_address_editShipping':
            case 'checkout_multishipping_shipping':
                return 'shipping';
            case 'checkout_multishipping_billing':
            case 'checkout_multishipping_address_selectBilling':
            case 'checkout_multishipping_address_newBilling':
            case 'checkout_multishipping_address_editBilling':
                return 'billing';
            case 'checkout_multishipping_overview':
                return 'checkout';
            case 'checkout_multishipping_success':
                return 'purchase';
        }
        return 'unknown';
    }
}
