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
 * Block with 'addCartRows' method implementation
 *
 * This block is independent of the other blocks because
 * there are special caching considerations for it.
 */
class Monetate_Track_Block_AddCartRows extends Monetate_Track_Block_Abstract
{
    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _getMonetateHtml()
    {
        $cartItems = $this->_getCartItems();
        if (count($cartItems)) {
            $html = '
<!-- Begin Monetate ExpressTag - AddCartRows -->
<script type="text/javascript">
//<![CDATA[
    window.monetateData.cartRows = ' . $this->helper('core')->jsonEncode($cartItems) . ';
//]]>
</script>
<!-- End Monetate ExpressTag -->
';
            return $html;
        }
        return '';
    }

    /**
     * Get items currently in the customers cart
     *
     * @return array
     */
    protected function _getCartItems()
    {
        $cartItems = array();
        $quote = Mage::getModel('checkout/cart')->getQuote();
        if ($quote->hasItems()) {
            $currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
            foreach ($quote->getAllItems() as $item) {
                if (($item->getParentItem() && $item->getParentItem()->getProduct()->getTypeId() != 'configurable')
                        || $item->getProduct()->getTypeId() == 'configurable') {
                    continue;
                } elseif ($item->getParentItem()
                        && $item->getParentItem()->getProduct()->getTypeId() == 'configurable') {
                    $price = $item->getParentItem()->getPrice();
                    $qty = (int) $item->getParentItem()->getQty();
                } else {
                    $price = $item->getPrice();
                    $qty = (int) $item->getQty();
                }
                $sku = str_replace(' ', '', $this->helper('core/string')->truncate($item->getSku(), 32, ''));
                $cartItems[] = array(
                        'productId'     => $item->getProductId(),
                        'quantity'      => $qty,
                        'unitPrice'     => number_format($price, '2', '.', ''),
                        'currency'      => $currencyCode,
                        'sku'           => $sku,
                );
            }
        }
        return $cartItems;
    }
}
