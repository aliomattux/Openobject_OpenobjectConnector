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

    public function __construct() {
        $this->_storeIdSessionField = 'product_store_id';
        $this->_ignoredAttributeTypes[] = 'gallery';
        $this->_ignoredAttributeTypes[] = 'media_image';
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

        $result = array();

        foreach ($collection as $product) {
            $result[] = $product->getId();
        }

        return $result;
    }


    public function allsqlsearch($filters = null, $store = null) {
        /*TODO: This function is clearly not good, but it is very fast. Refactor into something better */
        $this->_dbi = Mage::getSingleton('core/resource') ->getConnection('core_read');
        $query = "
                SELECT entity.entity_id FROM catalog_product_entity entity
                JOIN catalog_product_entity_int ints ON (entity.entity_id = ints.entity_id AND attribute_id IN (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'status'))
                WHERE ints.value = 1";

	   return $this->_dbi->fetchCol($query);
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


    public function multinfo($productIds) {
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
