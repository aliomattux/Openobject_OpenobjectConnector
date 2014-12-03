<?php
/**
Openobject Magento Connector
Generic API Extension for Magento Community/Enterprise Editions
This connector is a reboot of the original Openlabs OpenERP Connector
Copyright 2014 Kyle Waid
Copyright 2009 Openlabs / Sharoon Thomas
Some works Copyright by Mohammed NAHHAS
*/
class Openobject_OpenobjectConnector_Model_Oocatalog_Product_Tierprice extends Mage_Catalog_Model_Api_Resource {
    const ATTRIBUTE_CODE = 'tier_price';


    public function items($productIds=null) {
        if (is_array($productIds)) {
            $result = array ();
            foreach ($productIds as $productId) {
                $product = Mage :: getModel('catalog/product')->load($productId);
                if (!$product->getId()) {
                    $this->_fault('product_not_exists');
                }
                $tierPrices = $product->getData(self :: ATTRIBUTE_CODE);
                $result[$productId] = $tierPrices;
                    }
        }
        return $result;

    }

    public function items2($productIds=null) {
                $product = Mage :: getModel('catalog/product_attribute_backend_tierprice')->_get_set_go();
                if (!$product->getId()) {
                    $this->_fault('product_not_exists');
                }

                $tierPrices = $product->getPriceModel()->getTierPriceCount();
                return 'hello';
                $result[$productIds] = $tierPrices;


        return $result;


    }

}
