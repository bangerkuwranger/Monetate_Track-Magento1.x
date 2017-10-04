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
 * Export product feed settings model
 */
class Monetate_Track_Model_Adminhtml_ExportProductFeed extends Mage_Core_Model_Abstract
{
    /**
     * Outputs comment under the element in configuration
     *
     * @param Mage_Core_Model_Config_Element $element
     * @param string $currentValue
     *
     * @return string
     */
    public function getCommentText(Mage_Core_Model_Config_Element $element, $currentValue)
    {
        $helper = Mage::helper('monetate_track');
        $result  = '<a id="monetate_sftp_test_connection" href="#" onclick="sftp_connection_test();">' . $helper->__('Test SFTP Connection') . '</a><br />';
        $result .= '<a id="monetate_export_run" href="#" onclick="product_feed_export()">' . $helper->__('Run Product Feed Export') . '</a><br />';
        $result .= $helper->__('Product Feed Data Exports Statuses file is located in {{base_dir}}/var/monetate.log<br />');
        $result .= $helper->__('Product Feed Data Export Detailed files are located in {{base_dir}}/var/monetate/<br />');
        $result .= '<script type="text/javascript">';
        $result .= "
            function sftp_connection_test()
            {
                parameters = {
                    host:       '" . Mage::getStoreConfig('monetate/export/monetate_sftp_host') . "',
                    username:   $('monetate_export_sftp_user').getValue(),
                    password:   $('monetate_export_sftp_password').getValue(),
                    port:       '" . Mage::getStoreConfig('monetate/export/monetate_sftp_port') . "',
                    timeout:    '" . Mage::getStoreConfig('monetate/export/monetate_sftp_timeout') . "'
                };
                send_ajax_request('" . Mage::helper('adminhtml')->getUrl('monetate_track/adminhtml_sftp_connection/test') . "', parameters, 'monetate_sftp_test_connection', 'monetate_sftp_test_connection_result');
            }

            function product_feed_export()
            {
                parameters = {};
                send_ajax_request('" . Mage::helper('adminhtml')->getUrl('monetate_track/adminhtml_productfeed/export') . "', parameters, 'monetate_export_run', 'monetate_export_run_result');
            }

            function send_ajax_request(url, parameters, buttonId, resultId)
            {
                if ($(resultId) == undefined) {
                    $(buttonId).insert({after: '<span id=\'' + resultId + '\' class=\'bold\'>&nbsp;Processing</span>'});
                } else {
                    $(resultId).update('&nbsp;" . $helper->__('Processing') . "');
                    $(resultId).style.color = 'black';
                }
                new Ajax.Request(url, {
                    method:         'post',
                    parameters:     parameters,
                    timeout:        3000,
                    onSuccess:      function(response) {
                        if (response.responseText == 'Success') {
                            $(resultId).update('&nbsp;" . $helper->__('Success') . "');
                            $(resultId).style.color = 'green';
                        } else {
                            $(resultId).update('&nbsp;' + '" . $helper->__('Fail') . "');
                            $(resultId).style.color = 'red';
                            responseText = response.responseText
                                .replace(/(<([^>]+)>)/ig, '')
                                .replace(/^\\s*$[\\n\\r]{1,}/gm, '')
                                .split('\\n')[0];
                            console.log(responseText);
                        }
                    },
                    onFailure:      function(response) {
                        $(resultId).update('&nbsp;" . $helper->__('Fail') . "');
                        $(resultId).style.color = 'red';
                    },
                });
            }";
        $result .= '</script>';
        return $result;
    }
}
