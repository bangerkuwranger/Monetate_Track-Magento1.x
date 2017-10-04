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
 * Abstract Block with general functionality
 */
abstract class Monetate_Track_Block_Abstract extends Mage_Core_Block_Template
{
    /**
     * Returns HTML content to display
     *
     * @return string
     */
    abstract protected function _getMonetateHtml();

    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (Mage::getStoreConfigFlag('monetate/configuration/enable_tracking')) {
            return $this->_getMonetateHtml();
        }
        return '';
    }
}
