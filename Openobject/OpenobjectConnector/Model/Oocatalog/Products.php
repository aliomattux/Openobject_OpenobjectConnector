<?php
/**
*Openobject Magento Connector
*Generic API Extension for Magento Community/Enterprise Editions
*This connector is a reboot of the original Openlabs OpenERP Connector
*Copyright 2014 Kyle Waid
*Copyright 2009 Openlabs / Sharoon Thomas
*Some works Copyright by Mohammed NAHHAS
*/

class Openobject_OpenobjectConnector_Model_Oocatalog_Products extends Mage_Catalog_Model_Api_Resource {

    protected $_filtersMap = array(
        'product_id' => 'entity_id',
        'set'        => 'attribute_set_id',
        'type'       => 'type_id'
    );

    protected $_typeMap = array(
        'related'       => Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED,
        'up_sell'       => Mage_Catalog_Model_Product_Link::LINK_TYPE_UPSELL,
        'cross_sell'    => Mage_Catalog_Model_Product_Link::LINK_TYPE_CROSSSELL,
        'grouped'       => Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED
    );

    public function __construct() {
        $this->_storeIdSessionField = 'product_store_id';
        $this->_ignoredAttributeTypes[] = 'gallery';
        $this->_ignoredAttributeTypes[] = 'media_image';
    }


    protected function _initCollection($link, $product)
    {
     	$collection = $link
            ->getProductCollection()
            ->setIsStrongMode()
            ->setProduct($product);

        return $collection;
    }


    public function getProductLinks($product, $typeId, $identifierType = null) {
    //  $product = _initProduct($collectionItem->getId(), $identifierType);

        $link = $product->getLinkInstance()
            ->setLinkTypeId($typeId);

        $result = array();
        $collection = $this->_initCollection($link, $product);
        foreach ($collection as $linkedProduct) {
            $row = array(
                'product_id' => $linkedProduct->getId(),
                'type'       => $linkedProduct->getTypeId(),
                'set'        => $linkedProduct->getAttributeSetId(),
                'sku'        => $linkedProduct->getSku()
            );

            foreach ($link->getAttributes() as $attribute) {
                $row[$attribute['code']] = $linkedProduct->getData($attribute['code']);
            }

            $result[] = $row;
        }

        return $result;
    }

    public function retrievequotes($filters = null, $store = null) {
        $this->_dbi = Mage::getSingleton('core/resource') ->getConnection('core_read');
  //      $baseQuery = "SELECT * FROM quoteadv_customer WHERE quote_id = 4215366";
    //    $quote = $this->_dbi->fetchAll($baseQuery);

        $_collection = Mage::getModel('qquoteadv/qqadvcustomer')->getCollection();
        $_collection->addFieldToFilter('is_quote', 1);

        if (is_array($filters)) {
            try {
                foreach ($filters as $filter => $value) {
                    $_collection->addFieldToFilter("$filter", $value);
                }
            } catch (Mage_Core_Exception $e) {
                $this->_fault('filters_invalid', $e->getMessage());
            }
        }


        $result = array();
        foreach ($_collection as $order) {
            $order_array  = $order->toArray();

            $billingAddresses = Mage::getModel('qquoteadv/quoteaddress')
                    ->getCollection()
                    ->addFieldToFilter('quote_id', array('eq' => $order_array['quote_id']))
                    ->addFieldToFilter('address_type', array('eq' => 'billing'))
                    ->load();

            $billingAddress = $billingAddresses->getFirstItem();

            $shippingAddresses = Mage::getModel('qquoteadv/quoteaddress')
                    ->getCollection()
                    ->addFieldToFilter('quote_id', array('eq' => $order_array['quote_id']))
                    ->addFieldToFilter('address_type', array('eq' => 'shipping'))
                    ->load();

            $shippingAddress = $shippingAddresses->getFirstItem();

            $order_array['billing_address'] = $billingAddress->toArray();
            $order_array['shipping_address'] = $shippingAddress->toArray();


            $order_array['customer_firstname'] = $order_array['billing_address']['firstname'];
            $order_array['customer_lastname'] = $order_array['billing_address']['lastname'];
            $order_array['customer_email'] = $order_array['email'];

            $_itemsCollection = Mage::getModel('qquoteadv/qqadvproduct')->getCollection()
                ->addFieldToFilter('quote_id', $order_array['quote_id']);

            $order_array['items'] = array();

            foreach ($_itemsCollection as $orderItem) {
                $parentItem = $orderItem->toArray();

                $_deepItemCollection = Mage::getModel('qquoteadv/requestitem')->getCollection()
                    ->addFieldToFilter('quote_id', $order_array['quote_id'])
                    ->addFieldToFilter('quoteadv_product_id', $parentItem['id']);

                foreach ($_deepItemCollection as $deepItem) {
                    $dItem = $deepItem->toArray();
                    $finishedItem = array_replace($parentItem, $dItem);
                    $loadedItem = Mage::getModel('catalog/product')->load($dItem['product_id']);
                    $finishedItem['sku'] = $loadedItem->getSku();
                    $finishedItem['name'] = $loadedItem->getName();
                    $finishedItem['qty_ordered'] = $finishedItem['qty'];
                    $finishedItem['price'] = $finishedItem['original_cur_price'];
                    $finishedItem['parent_item_id'] = null;
                }

                $order_array['items'][] = $finishedItem;
            }


            $result[] = $order_array;
        }
        return $result;
    }

    public function filtersearch($filters = null, $store = null) {
        /*Search for product ids based on filters
        *This function is not good because it iterates over every result, which is very slow
        *TODO: Improve efficiency */

        $collection = Mage::getModel('catalog/product')->getCollection()
            ->setStoreId($this->_getStoreId($store))
            ->addAttributeToSelect('name');

        if (is_array($filters)) {
            try {
                foreach ($filters as $field => $value) {
                    if (isset($this->_filtersMap[$field])) {
                        $field = $this->_filtersMap[$field];
                    }

                    $collection->addFieldToFilter($field, $value);
                }
            }

	    catch (Mage_Core_Exception $e) {
                $this->_fault('filters_invalid', $e->getMessage());
            }
        }

        $result = $collection->getAllIds();

        return $result;
    }


    public function items($filters = null, $store = null) {
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->setStoreId($this->_getStoreId($store))
            ->addAttributeToSelect('name');

        if (is_array($filters)) {
            try {
                foreach ($filters as $field => $value) {
                    if (isset($this->_filtersMap[$field])) {
                        $field = $this->_filtersMap[$field];
                    }

                    $collection->addFieldToFilter($field, $value);
                }
            } catch (Mage_Core_Exception $e) {
                $this->_fault('filters_invalid', $e->getMessage());
            }
        }

        $result = array();
        foreach ($collection as $product) {
            $result[] = array(
                'product_id' => $product->getId(),
                'sku'        => $product->getSku(),
                'name'       => $product->getName(),
                'attribute_set_id'        => $product->getAttributeSetId(),
                'type'       => $product->getTypeId(),
                'category_ids'       => $product->getCategoryIds()
            );
        }

        return $result;
    }


    public function associatedproducts($productIds) {
        /*Get all associated simple products */

        $coreResource = Mage::getSingleton('core/resource');
        $conn = $coreResource->getConnection('core_read');

        $collection = Mage::getModel('catalog/product')
                ->getCollection()
                ->addAttributeToFilter('entity_id', array('in' => $productIds))
                ->addAttributeToSelect('entity_id');

        $result = array ();
        foreach ($collection as $collection_item) {
            $coll_array = $collection_item->toArray();
            $select = $conn->select()
                ->from($coreResource->getTableName('catalog/product_relation'), array('child_id'))
                ->where('parent_id = ?', $collection_item->getId());
                $coll_array['associated_products'] = $conn->fetchCol($select);

            $result[] = $coll_array;
        }

	   return $result;
    }


    public function multinfo($productIds, $includeLinks = false) {
	/* Fetch multiple products info */

	$store = null;
	$filters = null;
	$collection = Mage::getModel('catalog/product')
                ->getCollection()
                ->addAttributeToFilter('entity_id', array('in' => $productIds))
                ->addAttributeToSelect('*');

        $result = array ();

        foreach ($collection as $collection_item) {
            $coll_array = $collection_item->toArray();
            $coll_array['categories'] = $collection_item->getCategoryIds();
            $coll_array['websites'] = $collection_item->getWebsiteIds();
	        //If you want all kinds of links. Will make the call exponentially slower depending on number of links
	        if ($includeLinks) {
                if ($collection_item->getTypeId() == 'grouped') {
                    $coll_array['grouped'] = $this->getProductLinks($collection_item, Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED);
                }
                $coll_array['up_sell'] = $this->getProductLinks($collection_item, Mage_Catalog_Model_Product_Link::LINK_TYPE_UPSELL);
                $coll_array['cross_sell'] = $this->getProductLinks($collection_item, Mage_Catalog_Model_Product_Link::LINK_TYPE_CROSSSELL);
                $coll_array['related'] = $this->getProductLinks($collection_item, Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED);
	        }

            /*TODO: Put this into a single function as its used more than once */
            if ($collection_item->getTypeId() == 'configurable') {
                $attribute_array = $collection_item->getTypeInstance(true)->getConfigurableAttributesAsArray($collection_item);
                $attrs = array();
                foreach ($attribute_array as $attr) {
                    $attrs[] = $attr['attribute_id'];

                }

                $coll_array['super_attributes'] = $attrs;
	        }

            $result[] = $coll_array;

        }

        return $result;
    }


    public function getmultiplelinks($productIds) {
        $collection = Mage::getModel('catalog/product')
                ->getCollection()
                ->addAttributeToFilter('entity_id', array('in' => $productIds));

	$result = array ();
	foreach ($collection as $collection_item) {
	    $coll_array = $collection_item->toArray();
	    if ($collection_item->getTypeId() == 'grouped') {
		$coll_array['grouped'] = $this->getProductLinks($collection_item, Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED);
	    }
            $coll_array['up_sell'] = $this->getProductLinks($collection_item, Mage_Catalog_Model_Product_Link::LINK_TYPE_UPSELL);
            $coll_array['cross_sell'] = $this->getProductLinks($collection_item, Mage_Catalog_Model_Product_Link::LINK_TYPE_CROSSSELL);
            $coll_array['related'] = $this->getProductLinks($collection_item, Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED);

	    $result[] = $coll_array;
	}

	return $result;
    }


    public function info($productId) {
	/* Fetch one products info */
        $store = null;
        $filters = null;

        $collection = Mage::getModel('catalog/product')
                ->getCollection()
                ->addAttributeToFilter('entity_id', array('eq' => $productId))
                ->addAttributeToSelect('*');

        foreach ($collection as $collection_item) {
            $coll_array = $collection_item->toArray();
            $coll_array['categories'] = $collection_item->getCategoryIds();
            $coll_array['websites'] = $collection_item->getWebsiteIds();
            /*TODO: Put this into a single function as its used more than once */
            if ($collection_item->getTypeId() == 'configurable'){
                $attribute_array = $collection_item->getTypeInstance(true)->getConfigurableAttributesAsArray($collection_item);
                $attrs = array();

                foreach ($attribute_array as $attr) {
                    $attrs[] = $attr['attribute_id'];

                }

                $coll_array['super_attributes'] = $attrs;
            }

	        return $coll_array;
        }

    }


    public function create($type, $set, $sku, $productData) {
        /*TODO: Evaluate this function.
        *Create one Product
        */
        if (!$type || !$set || !$sku) {
            $this->_fault('data_invalid');
        }

        $product = Mage::getModel('catalog/product');
        /* @var $product Mage_Catalog_Model_Product */
        $product->setStoreId($this->_getStoreId($store))
            ->setAttributeSetId($set)
            ->setTypeId($type)
            ->setSku($sku);

        if (isset($productData['website_ids']) && is_array($productData['website_ids'])) {
            $product->setWebsiteIds($productData['website_ids']);
        }

        foreach ($product->getTypeInstance(true)->getEditableAttributes($product) as $attribute) {
            if ($this->_isAllowedAttribute($attribute)
                && isset($productData[$attribute->getAttributeCode()])) {
                $product->setData(
                    $attribute->getAttributeCode(),
                    $productData[$attribute->getAttributeCode()]
                );
            }
        }

        $this->_prepareDataForSave($product, $productData);

        if (is_array($errors = $product->validate())) {
            $this->_fault('data_invalid', implode("\n", $errors));
        }

        try {
            $product->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }

        return $product->getId();
    }


    public function update($productId, $productData = array(), $store = null) {
        $product = $this->_getProduct($productId, $store);

        if (!$product->getId()) {
            $this->_fault('not_exists');
        }

        if (isset($productData['website_ids']) && is_array($productData['website_ids'])) {
            $product->setWebsiteIds($productData['website_ids']);
        }

        foreach ($product->getTypeInstance(true)->getEditableAttributes($product) as $attribute) {
            if ($this->_isAllowedAttribute($attribute)
                && isset($productData[$attribute->getAttributeCode()])) {
                $product->setData(
                    $attribute->getAttributeCode(),
                    $productData[$attribute->getAttributeCode()]
                );
            }
        }

        $this->_prepareDataForSave($product, $productData);

        try {
            if (is_array($errors = $product->validate())) {
                $this->_fault('data_invalid', implode("\n", $errors));
            }
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }

        try {
            $product->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }

        return true;
    }


    protected function _prepareDataForSave ($product, $productData) {
        /*This function looks like trouble. When creating, website is already set. Seems redundant */
        if (isset($productData['categories']) && is_array($productData['categories'])) {
            $product->setCategoryIds($productData['categories']);
        }

        if (isset($productData['websites']) && is_array($productData['websites'])) {
            foreach ($productData['websites'] as &$website) {
                if (is_string($website)) {
                    try {
                        $website = Mage::app()->getWebsite($website)->getId();
                    } catch (Exception $e) { }
                }
            }
            $product->setWebsiteIds($productData['websites']);
        }

        if (isset($productData['stock_data']) && is_array($productData['stock_data'])) {
            $product->setStockData($productData['stock_data']);
        }
    }

    /**
     * Update product special price
     *
     * @param int|string $productId
     * @param float $specialPrice
     * @param string $fromDate
     * @param string $toDate
     * @param string|int $store
     * @return boolean
     */
    public function setSpecialPrice($productId, $specialPrice = null, $fromDate = null, $toDate = null, $store = null)
    {
        return $this->update($productId, array(
            'special_price'     => $specialPrice,
            'special_from_date' => $fromDate,
            'special_to_date'   => $toDate
        ), $store);
    }

    /**
     * Retrieve product special price
     *
     * @param int|string $productId
     * @param string|int $store
     * @return array
     */
    public function getSpecialPrice($productId, $store = null) {
        return $this->info($productId, $store, array('special_price', 'special_from_date', 'special_to_date'));
    }

    /**
     * Delete product
     *
     * @param int|string $productId
     * @return boolean
     */
    public function delete($productId) {
        $product = $this->_getProduct($productId);

        if (!$product->getId()) {
            $this->_fault('not_exists');
        }

        try {
            $product->delete();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('not_deleted', $e->getMessage());
        }

        return true;
    }
}
