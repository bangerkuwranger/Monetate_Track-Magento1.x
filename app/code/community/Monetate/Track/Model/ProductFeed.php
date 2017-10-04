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
 * Export products functionality
 */
class Monetate_Track_Model_ProductFeed extends Mage_Core_Model_Abstract
{
    /** Feed specification version */
    const PRODUCT_FEED_SPECIFICATION_VERSION = '5.0';

    /** Feed export files storage directory name */
    const PRODUCT_FEED_DIR = 'monetate';

    /** @var array Keys are product attributes, that are placed in product data array, and XML nodes' names as values */
    protected $_dataAttrs = array(
        'product_id'            => 'entity_id',
        'product_name'          => 'name',
        'product_description'   => 'description',
    );

    /** @var array Attributes to load for the product */
    protected $_productAttrs = array(
        'type_id', 'name', 'description', 'image', 'small_image',
        'price', 'special_price', 'special_from_date', 'special_to_date'
    );

    /** @var array Cache of Product Type Instances */
    protected $_productTypeInstances = array();

    /** @var Varien_Io_File */
    protected $_filesystem;

    /**
     * Set variables and open connection with local filesystem
     *
     * @see Varien_Object::_construct()
     */
    protected function _construct()
    {
        $this->setFilename('product-feed-export-' . date('y-m-d-H-i', time()) . '.xml');
        $this->setProductModel(Mage::getSingleton('catalog/product'));
        $this->setXmlDir(Mage::getBaseDir('var') . DS . self::PRODUCT_FEED_DIR . DS);
        $this->setExportResult(true);
        $this->_openFilesystemConnection();
        return parent::_construct();
    }

    /**
     * Export function
     *
     * @param bool $isLaunchedManually
     * @return string|bool
     */
    public function export($isLaunchedManually = false)
    {
        $message = 'Export process failed';
        $result = false;
        $exportEnabled = (bool) Mage::getStoreConfig('monetate/export/enable_cron');
        if ($exportEnabled || $isLaunchedManually) {
            Mage::log('Export process started', null, 'monetate.log', true);

            $doc = new DOMDocument('1.0', 'UTF-8');
            // Write XML header to the file
            $this->_saveXmlLocally($doc, false, false);
            // Write 'catalog' opening node and version node
            $this->_saveXmlLocally(false, $this->_getXmlStartAsText(), false);

            // Process the product collection
            $products = $this->getProductModel()->getCollection()->addAttributeToSelect($this->_productAttrs, 'left');
            $select = $products->getSelect();
            unset($products);
            Mage::getSingleton('core/resource_iterator')
                ->walk($select, array(array($this, 'productCallback')));

            // Write 'catalog' closing node
            $result = (bool) $this->_saveXmlLocally(false, $this->_getXmlEndAsText(), false);

            if ($result) {
                $message = 'Export process completed. Data saved in ' . $this->getFilename();
                if (!$this->_uploadXml()) {
                    $message .= ' (File not uploaded via SFTP)';
                }
            }
        }
        Mage::log($message, null, 'monetate.log', true);
        if (!$result) {
            $result = $message;
        }
        return $result;
    }

    /**
     * Get XML document's beginning as a text
     *
     * @return string
     */
    protected function _getXmlStartAsText()
    {
        return '<catalog><version>' . self::PRODUCT_FEED_SPECIFICATION_VERSION . '</version>';
    }

    /**
     * Get XML document's end as a text
     *
     * @return string
     */
    protected function _getXmlEndAsText()
    {
        return '</catalog>';
    }

    /**
     * Callback for walk() function. Run for each product.
     *
     * @param array $args
     */
    public function productCallback($args)
    {
        // Init Product (without loading)
        $product = $this->getProductModel()
            ->reset()
            ->setData($args['row']);
        $this->setProductTypeInstance($product);

        // Skip Virtual Products
        if ($product->getTypeId() === 'virtual') {
            return;
        }

        // Load Product Options
        if ($product->getHasOptions()) {
            foreach ($product->getProductOptionsCollection() as $option) {
                $option->setProduct($product);
                $product->addOption($option);
            }
        }

        // Add Product to XML
        $doc = new DOMDocument('1.0', 'UTF-8');
        $this->_createProductAttrsNodes($doc, $product);
        $result = (bool) $this->_saveXmlLocally($doc, false, $this->getFilename());
        if (!$result) {
            $this->setExportResult(false);
        }
        return;
    }

    /**
     * ReDefine Product Type Instance to Product
     *
     * @param Mage_Catalog_Model_Product $product
     */
    public function setProductTypeInstance(Mage_Catalog_Model_Product $product)
    {
        $type = $product->getTypeId();
        if (!isset($this->_productTypeInstances[$type])) {
            $this->_productTypeInstances[$type] = Mage::getSingleton('catalog/product_type')
            ->factory($product, true);
        }
        $product->setTypeInstance($this->_productTypeInstances[$type], true);
    }

    /**
     * Create product attributes' nodes
     *
     * @param DOMDocument                   $doc
     * @param Mage_Catalog_Model_Product    $product
     */
    protected function _createProductAttrsNodes(DOMDocument $doc, Mage_Catalog_Model_Product $product)
    {
        $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId());
        $productNode = $doc->createElement('product');
        $doc->appendChild($productNode);

        // product_id, product_name, product_description
        foreach ($this->_dataAttrs as $key => $value) {
            $node = $doc->createElement($key, htmlspecialchars(strip_tags($product->getData($value))));
            $productNode->appendChild($node);
        }

        // price, alt_price
        $this->_createPriceNodes($product, 'price', $doc, $productNode);

        // url
        $this->_createUrlNode($product, $doc, $productNode);

        // product_image_url
        $this->_createImageUrlNodes($product, 'product_image_url', $doc, $productNode);

        // categories
        $this->_createCategoriesNodes($product, $doc, $productNode);

        // brand_name
        $this->_createBrandNode($product, $doc, $productNode);

        // endcap_image_url
        $this->_createImageUrlNodes($product, 'endcap_image_url', $doc, $productNode);

        // search_image_url
        $this->_createImageUrlNodes($product, 'search_image_url', $doc, $productNode);

        // skus
        $this->_createSkuNodes($product, $doc, $productNode);

        // availability
        $this->_createStockNodes($stock, 'availability', 'is_in_stock', $doc, $productNode);

        // ats
        $this->_createStockNodes($stock, 'ats', 'min_qty', $doc, $productNode);

        // allocation
        $this->_createStockNodes($stock, 'allocation', 'qty', $doc, $productNode);

        // variations
        $this->_createVariationsNodes($product, $doc, $productNode);

        // rating
        $this->_createRatingNode($product, $doc, $productNode);
    }

    /**
     * Create category nodes
     *
     * @param Mage_Catalog_Model_Product $product
     * @param DOMDocument                $doc
     * @param DOMElement                 $productNode
     */
    protected function _createCategoriesNodes(Mage_Catalog_Model_Product $product, DOMDocument $doc,
        DOMElement $productNode)
    {
        $categoriesNode = $doc->createElement('categories');
        $productNode->appendChild($categoriesNode);
        $categories = $product->getCategoryCollection()->addAttributeToSelect('name');
        foreach ($categories as $category) {
            $categoryNode = $doc->createElement('category');
            $categoriesNode->appendChild($categoryNode);
            $categoryNameNode = $doc->createElement('category_name', htmlspecialchars($category->getName()));
            $categoryIdNode = $doc->createElement('category_id', $category->getId());
            $categoryNode->appendChild($categoryIdNode);
            $categoryNode->appendChild($categoryNameNode);
        }
    }

    /**
     * Create variations nodes
     *
     * @param Mage_Catalog_Model_Product $product
     * @param DOMDocument                $doc
     * @param DOMElement                 $productNode
     */
    protected function _createVariationsNodes(Mage_Catalog_Model_Product $product, DOMDocument $doc,
        DOMElement $productNode)
    {
        $variationsNode = $doc->createElement('variations');
        $productNode->appendChild($variationsNode);
        if ($product->getTypeId() === 'configurable') {
            $configurableAttributes = $product->getTypeInstance(true)
                ->getConfigurableAttributesAsArray($product);
            foreach ($configurableAttributes as $attr) {
                $label = strtolower(preg_replace('|[^a-z]*|i', '', $attr['label']));
                $attributeNode = $doc->createElement($label);
                $variationsNode->appendChild($attributeNode);
                foreach ($attr['values'] as $option) {
                    $label = strtolower(preg_replace('|[^a-z]*|i', '', $option['label']));
                    $optionNode = $doc->createElement('option', $label);
                    $attributeNode->appendChild($optionNode);
                }
            }
        }
        if ($product->getOptions()) {
            foreach ($product->getOptions() as $option) {
                $title = strtolower(preg_replace('|[^a-z]*|i', '', $option->getTitle()));
                $attributeNode = $doc->createElement($title);
                $variationsNode->appendChild($attributeNode);
                $values = $option->getValues();
                foreach ($values as $v) {
                    $optionNode = $doc->createElement('option', $v->getSku());
                    $attributeNode->appendChild($optionNode);
                }
            }
        }
    }

    /**
     * Create price nodes
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string                     $type
     * @param DOMDocument                $doc
     * @param DOMElement                 $productNode
     */
    protected function _createPriceNodes(Mage_Catalog_Model_Product $product, $type, DOMDocument $doc,
        DOMElement $productNode)
    {
        list($altPrice, $price) = $this->_getProductPrice($product, $type);
        $node = $doc->createElement('price', number_format($price, 2, '.', ''));
        $productNode->appendChild($node);
        $node = $doc->createElement('alt_price', number_format($altPrice, 2, '.', ''));
        $productNode->appendChild($node);
    }

    /**
     * Return product price
     *
     * @param Mage_Catalog_Model_Product $product
     *
     * @return array
     */
    protected function _getProductPrice(Mage_Catalog_Model_Product $product)
    {
        if ($product->getTypeId() === 'bundle') {
            $priceModel = $product->getPriceModel();
            $stores = Mage::app()->getStores();
            $price = 0;
            foreach ($stores as $store) {
                $product->setStoreId($store->getId());
                if (method_exists($priceModel, 'getTotalPrices')) {
                    list($minimalPrice, $maximalPrice) = $priceModel->getTotalPrices($product, null, null, false);
                } else {
                    list($minimalPrice, $maximalPrice) = $priceModel->getPricesDependingOnTax($product);
                }
                if ($price == 0 || $price > (float) $minimalPrice) {
                    $price = $minimalPrice;
                }
            }
            return array($price, $product->getFinalPrice());
        } elseif ($product->getTypeId() === 'grouped') {
            $associatedProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);
            $price = array();
            foreach ($associatedProducts as $item) {
                $price[] = $item->getFinalPrice();
            }
            sort($price);
            return array($price[0], $product->getFinalPrice());
        } elseif ($product->getTypeId() === 'giftcard' && $product->getGiftcardAmounts()) {
            $prices = Mage::getBlockSingleton('enterprise_giftcard/catalog_product_view_type_giftcard')
                ->getAmounts($product);
            if ($prices) {
                return $prices[0];
            }
            return array(0, $product->getFinalPrice());
        } else {
            return array($product->getPrice(), $product->getFinalPrice());
        }
    }

    /**
     * Create ats, availability, allocation nodes
     *
     * @param Mage_CatalogInventory_Model_Stock_Item    $stock
     * @param string                                    $nodeName
     * @param string                                    $attrName
     * @param DOMDocument                               $doc
     * @param DOMElement                                $productNode
     */
    protected function _createStockNodes(Mage_CatalogInventory_Model_Stock_Item $stock, $nodeName, $attrName,
        DOMDocument $doc, DOMElement $productNode)
    {
        if ($attrName == 'is_in_stock') {
            $value = $stock->getData('is_in_stock') ? 'In Stock' : 'Out of Stock';
        } else {
            $value = (int) $stock->getData($attrName);
        }
        $node = $doc->createElement($nodeName, $value);
        $productNode->appendChild($node);
    }

    /**
     * Create image urls nodes
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string                     $type
     * @param DOMDocument                $doc
     * @param DOMElement                 $productNode
     */
    protected function _createImageUrlNodes(Mage_Catalog_Model_Product $product, $type, DOMDocument $doc,
        DOMElement $productNode)
    {
        $url = '';
        try {
            if ($type == 'endcap_image_url') {
                $url = Mage::getDesign()
                    ->getSkinUrl('images/catalog/product/placeholder/small_image.jpg', array('_area' => 'frontend'));
            } elseif ($type == 'product_image_url' && $product->getData('image')
                && $product->getData('image') != 'no_selection') {
                    $url = Mage::helper('catalog/image')->init($product, 'image');
            } elseif ($type == 'search_image_url' && $product->getData('small_image')
                && $product->getData('small_image') != 'no_selection') {
                    $url = Mage::helper('catalog/image')->init($product, 'small_image');
            }
            $url = str_replace(Mage::getStoreConfig('web/unsecure/base_url'), Mage::getStoreConfig('web/secure/base_url'), $url);
            $imageUrlNode = $doc->createElement($type, $url);
            $productNode->appendChild($imageUrlNode);
        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'monetate.log', true);
        }
    }

    /**
     * Create product url node
     *
     * @param Mage_Catalog_Model_Product $product
     * @param DOMDocument                $doc
     * @param DOMElement                 $productNode
     */
    protected function _createUrlNode(Mage_Catalog_Model_Product $product, DOMDocument $doc, DOMElement $productNode)
    {
        $url = $product->getProductUrl();
        $urlNode = $doc->createElement('url', $url);
        $productNode->appendChild($urlNode);
    }

    /**
     * Create rating node
     *
     * @param Mage_Catalog_Model_Product $product
     * @param DOMDocument                $doc
     * @param DOMElement                 $productNode
     */
    protected function _createRatingNode(Mage_Catalog_Model_Product $product, DOMDocument $doc, DOMElement $productNode)
    {
        $summaryData = Mage::getModel('review/review_summary')->load($product->getId());
        $ratingNode = $doc->createElement('rating', (int) $summaryData->getRatingSummary());
        $productNode->appendChild($ratingNode);
    }

    /**
     * Create skus nodes
     *
     * @param Mage_Catalog_Model_Product $product
     * @param DOMDocument                $doc
     * @param DOMElement                 $productNode
     */
    protected function _createSkuNodes(Mage_Catalog_Model_Product $product, DOMDocument $doc, DOMElement $productNode)
    {
        $skusNode = $doc->createElement('skus');
        $skuNode = $doc->createElement('sku', $product->getSku());
        $skusNode->appendChild($skuNode);
        $productNode->appendChild($skusNode);
    }

    /**
     * Create brand node
     *
     * @param Mage_Catalog_Model_Product $product
     * @param DOMDocument                $doc
     * @param DOMElement                 $productNode
     */
    protected function _createBrandNode(Mage_Catalog_Model_Product $product, DOMDocument $doc, DOMElement $productNode)
    {
        $brandNode = $doc->createElement('brand_name', htmlspecialchars($product->getAttributeText('manufacturer')));
        $productNode->appendChild($brandNode);
    }

    /**
     * Saves document on server
     *
     * @param DOMDocument|false     $doc
     * @param string|false          $content
     * @param bool                  $excludeHeader
     *
     * @return bool|string
     */
    protected function _saveXmlLocally($doc, $content, $excludeHeader = true)
    {
        $result = false;
        $this->_filesystem->checkAndCreateFolder($this->getXmlDir());
        if ($excludeHeader && !$content) {
            $content = $doc->saveXml($doc->documentElement);
        } elseif (!$content) {
            $content = $doc->saveXml();
        }
        if ($content) {
            $result = @file_put_contents($this->getXmlDir() . $this->getFilename(), $content, FILE_APPEND);
        }
        return $result;
    }

    /**
     * Upload document to Monetate server via SFTP
     *
     * @return string|bool
     */
    protected function _uploadXml()
    {
        // Close connection with local filesystem
        $this->_filesystem->cd(getcwd());

        $accessData = array(
            'host'      => Mage::getStoreConfig('monetate/export/monetate_sftp_host'),
            'username'  => Mage::getStoreConfig('monetate/export/sftp_user'),
            'password'  => Mage::getStoreConfig('monetate/export/sftp_password'),
            'port'      => Mage::getStoreConfig('monetate/export/monetate_sftp_port'),
            'timeout'   => Mage::getStoreConfig('monetate/export/monetate_sftp_timeout'),
        );
        if (empty($accessData['host']) || empty($accessData['username']) || empty($accessData['password'])) {
            return false;
        }
        include('phpseclib/Net/SFTP.php');
        $netSftpConnection = new Net_SFTP($accessData['host'], $accessData['port'], $accessData['timeout']);
        if (!$netSftpConnection->login($accessData['username'], $accessData['password'])) {
            Mage::log('Login on SFTP server failed', null, 'monetate.log', true);
        } else if ($netSftpConnection->put('/upload/' . $this->getFilename(), $this->getXmlDir() . $this->getFilename(),
            NET_SFTP_LOCAL_FILE)) {
                return $this->getXmlDir() . $this->getFilename();
        }
        return false;
    }

    /**
     * Open connection with local filesystem
     */
    protected function _openFilesystemConnection()
    {
        $this->_filesystem = new Varien_Io_File;
        $this->_filesystem->open();
        $this->_filesystem->cd($this->_filesystem->pwd());
    }
}
