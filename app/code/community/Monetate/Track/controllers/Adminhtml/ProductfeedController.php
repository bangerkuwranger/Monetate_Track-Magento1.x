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
 * Product feed data econtroller
 */
class Monetate_Track_Adminhtml_ProductfeedController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Export action
     */
    public function exportAction()
    {
        if ($this->getRequest()->isAjax()) {
            try {
                $result = Mage::getSingleton('monetate_track/productFeed')->export(true);
                if ($result === true) {
                    echo 'Success';
                } else {
                    echo $result;
                }
            } catch (Exception $e) {
                Mage::log($e->getMessage(), null, 'monetate.log', true);
                echo $this->__($e->getMessage());
            }
        } else {
            return $this->_redirect('*/*');
        }
    }
}
