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
 * Block with 'trackData' method implementation
 */
class Monetate_Track_Block_TrackData extends Monetate_Track_Block_Abstract
{
    /**
     * Push items to render
     */
    protected $_pushItems = array();

    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _getMonetateHtml()
    {
        //Start Javascript
        $html = '
<!-- Begin Monetate ExpressTag - TrackData -->
<script type="text/javascript">
//<![CDATA[
    window.monetateQ.push(["setPageType", window.monetateData.pageType]);
    if(window.monetateData.cartRows) window.monetateQ.push(["addCartRows", window.monetateData.cartRows]);';

        //Add Push Items
        $this->addPushItem('trackData');
        foreach ($this->_pushItems as $key => $data) {
            if ($data === null) {
                $html .= '
    window.monetateQ.push(["' . $key . '"]);';
            } else {
                $html .= '
    window.monetateQ.push(["' . $key . '", ' . $this->helper('core')->jsonEncode($data) . ']);';
            }
        }

        //Add Onepage Checkout Observer
        if ($this->getOnepageEnabled()) {
            $html .= '

    if (window.checkout != undefined && window.checkout.accordion != undefined
        && typeof window.checkout.accordion.openSection === "function") {
        window.checkout.accordion.openSection = function(section){
            Accordion.prototype.openSection.call(this, section);
            var map = { "opc-login":"checkoutLogin", "opc-billing":"billing", "opc-shipping":"shipping",
                "opc-shipping_method":"shipping", "opc-payment":"billing", "opc-review":"checkout" };
            window.monetateData.pageType = map[this.currentSection] || "unknown";
            window.monetateQ.push(["setPageType", window.monetateData.pageType]);
            if(window.monetateData.cartRows) window.monetateQ.push(["addCartRows", window.monetateData.cartRows]);
            window.monetateQ.push(["trackData"]);
        };
    }';
        }

        //Finish Javascript
        $html .= '
//]]>
</script>
<!-- End Monetate ExpressTag -->
';
        return $html;
    }

    /**
     * Edit item to push data
     *
     * @param string $key
     * @param mixed $data
     */
    public function addPushItem($key, $data = null)
    {
        $this->_pushItems[$key] = $data;
    }

    /**
     * Implement 'addCategories' functionality
     */
    public function addCategories()
    {
        $category = Mage::registry('current_category');
        if ($category->getId()) {
            $this->addPushItem('addCategories', array($category->getId()));
        }
    }

    /**
     * Implement 'addProducts' functionality
     *
     * @param Mage_Catalog_Model_Resource_Product_Collection $collection
     */
    public function addProducts($collection)
    {
        if ($collection instanceof Varien_Data_Collection) {
            $productIds = $collection->getColumnValues('entity_id');
            if (count($productIds)) {
                $this->addPushItem('addProducts', $productIds);
            }
        }
    }

    /**
     * Implement 'addProductDetails' functionality
     */
    public function addProductDetails()
    {
        $product = Mage::registry('current_product');
        if ($product->getId()) {
            $productIds = array($product->getId());
            if ($product->getTypeId() == 'grouped') {
                $associatedProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);
                if ($associatedProducts) {
                    $productIds = array();
                    foreach ($associatedProducts as $associatedProduct) {
                        $productIds[] = $associatedProduct->getId();
                    }
                }
            }
            $this->addPushItem('addProductDetails', $productIds);
        }
    }

    /**
     * Implement 'addPurchaseRows' functionality
     */
    public function addPurchaseRows($orderIds)
    {
        if (is_array($orderIds)) {
            $purchasedRows = array();
            foreach ($orderIds as $orderId) {
                $order = Mage::getModel('sales/order')->load($orderId);
                if ($order->getId()) {
                    $currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
                    foreach ($order->getAllItems() as $item) {
                        $price = $qty = 0;
                        $parent = $item->getParentItem();
                        if (($parent !== null && $parent->getProductType() != 'configurable')
                            || $item->getProductType() == 'configurable') {
                            continue;
                        } elseif ($parent && $parent->getProductType() == 'configurable') {
                            $price = $item->getParentItem()->getPrice();
                            $qty = (int) $item->getParentItem()->getQtyOrdered();
                        } else {
                            $price = $item->getPrice();
                            $qty = (int) $item->getQtyOrdered();
                        }
                        $sku = str_replace(' ', '', $this->helper('core/string')->truncate($item->getSku(), 32, ''));
                        $purchasedRows[] = array(
                            'purchaseId'    => $order->getIncrementId(),
                            'productId'     => $item->getProductId(),
                            'quantity'      => (int) $qty,
                            'unitPrice'     => number_format($price, '2', '.', ''),
                            'currency'      => $currencyCode,
                            'sku'           => $sku,
                        );
                    }
                }
            }
            if (count($purchasedRows)) {
                $this->addPushItem('addPurchaseRows', $purchasedRows);
            }
        }
    }
}
