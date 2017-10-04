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
 * Monetate_Track module's observer
 */
class Monetate_Track_Model_Observer
{
    /**
     * Add Monetate Tag script to head
     *
     * @param Varien_Event_Observer $observer
     */
    public function observeBlockShowing(Varien_Event_Observer $observer)
    {
        $block = $observer->getBlock();
        if ($block instanceof Mage_Page_Block_Html_Head) {
            $transport = $observer->getTransport();
            $html = $this->_htmlJsInsert($transport->getHtml(), Mage::getStoreConfig('monetate/configuration/tag'));
            $transport->setHtml($html);
        }
    }

    /**
     * Add 'addProductsBlock' block's HTML to blocks' HTML with type 'catalog/product_list'
     *
     * @param Varien_Event_Observer $observer
     */
    public function observeProductListCollection(Varien_Event_Observer $observer) {
        $block = Mage::app()->getLayout()->getBlock('monetate.track.track.data');
        if ($block instanceof Monetate_Track_Block_TrackData) {
            $block->addProducts($observer->getCollection());
        }
    }

    /**
     * Add Purchase Rows to page
     *
     * @param Varien_Event_Observer $observer
     */
    public function observeOrderSuccess(Varien_Event_Observer $observer)
    {
        $block = Mage::app()->getLayout()->getBlock('monetate.track.track.data');
        if ($block instanceof Monetate_Track_Block_TrackData) {
            $block->addPurchaseRows($observer->getOrderIds());
        }
    }

	/**
	 * Insert script before other scripts in HTML code
	 *
	 * @param string $html
	 * @param string $tagHtml
	 */
	protected function _htmlJsInsert($html, $tagHtml)
	{
		if (mb_strpos($html, '<script', null, 'utf-8') < mb_strpos($html, '<!--[if', null, 'utf-8')) {
            $scriptStartPos = mb_strpos($html, '<script', null, 'utf-8');
        } else {
            $scriptStartPos = mb_strpos($html, '<!--[if', null, 'utf-8');
        }
        $htmlBefore = mb_substr($html, 0, $scriptStartPos);
        $htmlAfter = mb_substr($html, $scriptStartPos, mb_strlen($html, 'utf-8') - $scriptStartPos);

        return $htmlBefore . $tagHtml . $htmlAfter;
	}
}
