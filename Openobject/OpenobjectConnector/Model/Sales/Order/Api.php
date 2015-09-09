<?php
/**
*Openobject Magento Connector
*Generic API Extension for Magento Community/Enterprise Editions
*This connector is a reboot of the original Openlabs OpenERP Connector
*Copyright 2014 Kyle Waid
*Copyright 2009 Openlabs / Sharoon Thomas
*Some works Copyright by Mohammed NAHHAS
*/

class Openobject_OpenobjectConnector_Model_Sales_Order_Api extends Mage_Sales_Model_Order_Api {

    /**
     * Return the list of products ids that match with the filter
     * The filter imported is required
     * @param  array
     * @return array
     */

    public function multiload($increment_ids) {
	// Load Order Collection
	$order_collection = Mage::getModel('sales/order')
	->getCollection()
	->addAttributeToSelect('*')
	->addAttributeToFilter('increment_id', array('in' => $increment_ids));

	$result = array();
	foreach ($order_collection as $order) {
	    $order_array  = $order->toArray();
	    $order_array['order_id'] = $order->getId();
	    $order_array['tax_identification'] = $order->getFullTaxInfo();
	    foreach ($order->getAllItems() as $item) {
	        if ($item->getGiftMessageId() > 0) {
		    $item->setGiftMessage(
			Mage::getSingleton('giftmessage/message')->load($item->getGiftMessageId())->getMessage()
		    );
		 }
		$order_array['items'][] = $this->_getAttributes($item, 'order_item');
	    }

	    $order_array['payment'] = $this->_getAttributes($order->getPayment(), 'order_payment');
	    $order_array['shipping_address'] = $this->_getAttributes($order->getShippingAddress(), 'order_address');
	    $order_array['billing_address'] = $this->_getAttributes($order->getBillingAddress(), 'order_address');
            //This only works if you have cart2quote installed.... what to do about this.
	    $result[] = $order_array;

	}

	return $result;
    }


    public function search($filters = null, $store = null) {
        $collection = Mage::getModel('sales/order')->getCollection()
        ->addAttributeToSelect('increment_id');
         //   ->setStoreId($this->_getStoreId($store));

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

        $result = $collection->getData();

        return $result;
    }

    /**
     *
     * Retrieve orders data based on the value of the flag 'imported'
     * @param  array
     * @return array
     */
    public function retrieveOrders($data) {

        $result = array();
        if(isset($data['imported'])) {

            $collection = Mage::getModel("sales/order")->getCollection()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('imported', array('eq' => $data['imported']));

            /* addAddressFields() is called only if version >= 1400 */
            if(str_replace('.','',Mage::getVersion()) >= 1400) {
                $collection->addAddressFields();
            }

            if(isset($data['limit'])) {
                $collection->setPageSize($data['limit']);
                $collection->setOrder('entity_id', 'ASC');
            }

            if(isset($data['filters']) && is_array($data['filters'])) {
                $filters = $data['filters'];
                foreach($filters as $field => $value) {
                    $collection->addAttributeToFilter($field, $value);
                }
            }

            foreach ($collection as $order) {
                $tmp = $this->_getAttributes($order, 'order');

                /* if version < 1400, billing and shipping information are added manually to order data */
                if(str_replace('.','',Mage::getVersion()) < 1400) {
                    $address_data = $this->_getAttributes($order->getShippingAddress(), 'order_address');
                    if(!empty($address_data)) {
                        $tmp['shipping_firstname'] = $address_data['firstname'];
                        $tmp['shipping_lastname'] = $address_data['lastname'];
                    }

                    $address_data = $this->_getAttributes($order->getBillingAddress(), 'order_address');
                    if(!empty($address_data)) {
                        $tmp['billing_firstname'] = $address_data['firstname'];
                        $tmp['billing_lastname'] = $address_data['lastname'];
                    }
                }

                $result[] = $tmp;
            }
            return $result;
        }else{
            $this->_fault('data_invalid', "Error, the attribut 'imported' need to be specified");
        }
    }

    public function setFlagForOrder($incrementId) {
        $_order = $this->_initOrder($incrementId);
        $_order->setImported(1);
        try {
            $_order->save();
            return true;
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }
    }

    /* Retrieve increment_id of the child order */
    public function getOrderChild($incrementId) {

        $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
        /**
          * Check order existing
          */
        if (!$order->getId()) {
             $this->_fault('order_not_exists');
        }

        if($order->getRelationChildId()) {
            return $order->getRelationChildRealId();
        }else{
            return false;
        }
    }

    /* Retrieve increment_id of the parent order */
    public function getOrderParent($incrementId) {

        $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
        /**
          * Check order existing
          */
        if (!$order->getId()) {
             $this->_fault('order_not_exists');
        }

        if($order->getRelationParentId()) {
            return $order->getRelationParentRealId();
        }else{
            return false;
        }
    }


    /* Retrieve order states */
    public function getOrderStates() {
        return Mage::getSingleton("sales/order_config")->getStates();
    }

    /* Retrieve order statuses */
    public function getStateApiStatuses($state) {
        return Mage::getSingleton("sales/order_config")->getStateStatuses($state);
    }


    /* Retrieve invoices increment ids of the order */
    public function getInvoiceIds($incrementId) {
        $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
        /**
          * Check order existing
        */
        if (!$order->getId()) {
             $this->_fault('order_not_exists');
        }
        $res = array();
        foreach($order->getInvoiceCollection() as $invoice){
            array_push($res, $invoice->getIncrementId());
        };
        return $res;
    }

    /* Retrieve shipment increment ids of the order */
    public function getShipmentIds($incrementId) {
        $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
        /**
          * Check order existing
        */
        if (!$order->getId()) {
             $this->_fault('order_not_exists');
        }
        $res = array();
        foreach($order->getShipmentsCollection() as $shipping){
            array_push($res, $shipping->getIncrementId());
        };
        return $res;
    }

    public function get_old_all_shipping_methods()
    {
        $methods = Mage::getSingleton('shipping/config')->getActiveCarriers();

        $options = array();
        foreach($methods as $_code => $_method)
        {
            if(!$_title = Mage::getStoreConfig("carriers/$_code/title"))
                $_title = $_code;

            $options[] = array('code' => $_code, 'label' => $_title);
        }
        return $options;
    }

    public function get_taxes_info() {
        $this->_dbi = Mage::getSingleton('core/resource') ->getConnection('core_read');
        $query = "SELECT rate.rate, rate.code FROM tax_class tc JOIN tax_calculation calc ON (calc.product_tax_class_id = tc.class_id) JOIN tax_calculation_rate rate ON (calc.tax_calculation_rate_id = rate.tax_calculation_rate_id) GROUP BY rate.rate, rate.code";
        $res = $this->_dbi->fetchAll($query);
        return $res;

    }

    public function get_all_shipping_methods() {
        $activeCarriers = Mage::getSingleton('shipping/config')->getActiveCarriers();

        $allCarriers = array();

        foreach($activeCarriers as $carrierCode => $carrierModel) {
            $options = array();

            if( $carrierMethods = $carrierModel->getAllowedMethods() ) {
                $carrierTitle = Mage::getStoreConfig(‘carriers/’.$carrierCode.’/title’);

                foreach ($carrierMethods as $methodCode => $method) {
                    $code = $carrierCode.'_'.$methodCode;
                    $options[] = array('carrier_code' => $carrierCode, 'method_code'=> $code, 'method_title'=> $method); 
                }

            $allCarriers[] = array(
                    'carrier_title' => Mage::getStoreConfig("carriers/$_code/title"),
                    'carrier_code' => $carrierCode,
                    'methods' => $options,
                );

            }

            $methods[]=array('value'=>$options,'label'=>$carrierTitle);
        }

        return $allCarriers;
    }


    public function getRandomHash($length = 40) {
        $max = ceil($length / 40);
        $random = '';
        for ($i = 0; $i < $max; $i++) {
            $random .= sha1(microtime(true) . mt_rand(10000, 90000));
        }
        return substr($random, 0, $length);
    }


    public function getRegionId($countryId, $stateName) {
        $regionData = Mage::getModel('directory/region')
        ->getCollection()
        ->addFieldToFilter('country_id', $countryId)
        ->addFieldToFilter('default_name', $stateName)
        ->addFieldToSelect('region_id')
        ->getFirstItem()->getData();
        if ($regionData) {
            return $regionData['region_id'];
        }

        return null;

    }


    public function createQuoteAddress($addressType, $quoteId, $Data) {
        $addressModel = Mage::getModel('qquoteadv/quoteaddress');
        $addressData = array(
            'updated_at' => $Data['updated_at'],
            'created_at' => $Data['created_at'],
            'save_in_address_book' => $Data['save_in_address_book'],
            'customer_id' => $Data['customer_id'],
            'quote_id' => $quoteId,
            'customer_address_id' => $Data['customer_address_id'],
            'address_type' => $addressType,
            'email' => $Data['email'],
            'firstname' => $Data['firstname'],
            'lastname' => $Data['lastname'],
            'company' => $Data['company'],
            'street' => $Data['street'],
            'city' => $Data['city'],
            'country_id' => $Data['country_id'],
            'region' => $Data['region'],
            'postcode' => $Data['postcode'],
            'region_id' => $this->getRegionId($Data['country_id'], $Data['region']),
            'telephone' => $Data['telephone'],
            'same_as_billing' => $Data['same_as_billing'],
            'shipping_method' => $Data['shipping_method'],
            'subtotal' => $Data['subtotal'],
            'base_subtotal' => $Data['base_subtotal'],
            'subtotal_with_discount' => $Data['subtotal_with_discount'],
            'base_subtotal_with_discount' => $Data['base_subtotal_with_discount'],
            'tax_amount' => $Data['tax_amount'],
            'base_tax_amount' => $Data['base_tax_amount'],
            'shipping_amount' => $Data['shipping_amount'],
            'base_shipping_amount' => $Data['base_shipping_amount'],
            'shipping_tax_amount' => $Data['shipping_tax_amount'],
            'base_shipping_tax_amount' => $Data['base_shipping_tax_amount'],
            'discount_amount' => $Data['discount_amount'],
            'base_discount_amount' => $Data['base_discount_amount'],
            'grand_total' => $Data['grand_total'],
            'base_grand_total' => $Data['base_grand_total'],
            'customer_notes' => $Data['customer_notes'],

        );

        $addressModel->setData($addressData);
        return $addressModel->save();
    }


    public function addItem($quoteId, $Data) {
        $productId = $Data['product_id'];
        $qty = $Data['qty'];
        $itemModel = Mage::getModel( 'qquoteadv/qqadvproduct' );
        $itemData = array(
            'quote_id' => $quoteId,
            'store_id' => $Data['store_id'],
            'product_id' => $productId,
            'qty' => $qty,
            'attribute' => $Data['attribute'],
            'has_options' => $Data['has_options'],
        );
        $itemModel->setData($itemData);
        $itemModel->save();
        $this->_dbi = Mage::getSingleton('core/resource') ->getConnection('core_read');
        $baseQuery = "SELECT id FROM quoteadv_product WHERE quote_id = {$quoteId} AND product_id = {$productId} AND qty = {$qty}";
        $itemData = $this->_dbi->fetchCol($baseQuery);
        $itemId = $itemData[0];
        return $itemId;
    }


    public function addRequestItem($quoteId, $itemId, $Data) {
        $requestModel = Mage::getModel( 'qquoteadv/requestitem' );
        $productId = $Data['product_id'];
        $requestQty = $Data['request_qty'];
        $ownerBasePrice = $Data['owner_base_price'];
        $originalPrice = $Data['original_price'];
        $originalCurPrice = $Data['original_cur_price'];
        $ownerCurPrice = $Data['owner_cur_price'];
        $quoteadvProductid = $itemId;

        $this->_dbi = Mage::getSingleton('core/resource') ->getConnection('core_read');
        $baseQuery = "INSERT INTO quoteadv_request_item (quote_id, product_id, request_qty, owner_base_price, original_price, original_cur_price, owner_cur_price, quoteadv_product_id) VALUES ({$quoteId}, {$productId}, {$requestQty}, {$ownerBasePrice}, {$originalPrice}, {$originalCurPrice}, {$ownerCurPrice}, {$quoteadvProductid})";
        $itemData = $this->_dbi->query($baseQuery);
       // $requestModel->setData();
     //   $requestModel->save();
        return;
    }


    public function createQuote($quoteData) {
        //Pass your own
         $incrementId = Mage::getModel( 'qquoteadv/entity_increment_numeric' )->getNextId();
       // $incrementId = $quoteData['increment_id'];
        $createHash = Mage::helper( 'qquoteadv/license' )->getCreateHash($incrementId);
        $hash = $this->getRandomHash(40);
       // $incrementId = $quoteData['increment_id'];
        $billingData = $quoteData['billing_address'];
        $shippingData = $quoteData['shipping_address'];

        $quoteModel = Mage::getModel( 'qquoteadv/qqadvcustomer' );
        $qcustomer = array(
            'increment_id' => $incrementId,
            'is_quote' => $quoteData['is_quote'],
            'status' => $quoteData['status'],
            'firstname' => $quoteData['firstname'],
            'lastname' => $quoteData['lastname'],
            'company' => $quoteData['company'],
            'email' => $quoteData['email'],
            'updated_at' => $quoteData['updated_at'],
            'created_at' => $quoteData['created_at'],
            'client_request' => $quoteData['client_request'],
            'country_id' => $quoteData['country_id'],
            'telephone' => $quoteData['telephone'],
            'store_id' => $quoteData['store_id'],
            'region' => $quoteData['region'],
            'city' => $quoteData['city'],
            'postcode' => $quoteData['postcode'],
            'shipping_firstname' => $quoteData['shipping_firstname'],
            'shipping_lastname' => $quoteData['shipping_lastname'],
            'shipping_country_id' => $quoteData['shipping_country_id'],
            'shipping_telephone' => $quoteData['shipping_phone'],
            'shipping_postcode' => $quoteData['shipping_postcode'],
            'user_id' => $quoteData['admin_user_id'],
            'shipping_method_title' => $quoteData['shipping_method_title'],
            'shipping_carrier' => $quoteData['shipping_carrier'],
            'shipping_carrier_title' => $quoteData['shipping_carrier_title'],
            'shipping_code' => $quoteData['shipping_code'],
            'shipping_description' => $quoteData['shipping_description'],
            'currency' => $quoteData['currency'],
            'shipping_base_price' => $quoteData['shipping_base_price'],
            'imported' => $quoteData['imported'],
            'base_to_quote_rate' => $quoteData['base_to_quote_rate'],
            'expiry' => $quoteData['expiry'],
            'itemprice' => $quoteData['itemprice'],
            'base_subtotal' => $quoteData['base_subtotal'],
            'base_grand_total' => $quoteData['base_grand_total'],
            'base_shipping_amount' => $quoteData['base_shipping_amount'],
            'base_tax_amount' => $quoteData['base_tax_amount'],
            'shipping_amount' => $quoteData['shipping_amount'],
            'grand_total' => $quoteData['grand_total'],
            'tax_amount' => $quoteData['tax_amount'],
            'subtotal' => $quoteData['subtotal'],
            'subtotal_incl_tax' => $quoteData['subtotal_incl_tax'],
            'shipping_incl_tax' => $quoteData['shipping_incl_tax'],
            'customer_id' => $quoteData['customer_id'],
            'create_hash' => $createHash,
            'hash' => $hash,

            );

            $quoteModel->setData($qcustomer);
            $quoteModel->save();
            $this->_dbi = Mage::getSingleton('core/resource') ->getConnection('core_read');
            $baseQuery = "SELECT quote_id FROM quoteadv_customer WHERE increment_id = '{$incrementId}'";
            $quoteResult = $this->_dbi->fetchCol($baseQuery);
            $quoteId = $quoteResult[0];



            #################   Create Addresses   #######################
            $billingAddress = $this->createQuoteAddress('billing', $quoteId, $billingData);
            $shippingAddress = $this->createQuoteAddress('shipping', $quoteId, $shippingData);



            #################   Create Items   ##########################
            $items = $quoteData['items'];

            foreach($items as $item) {
                $itemId = $this->addItem($quoteId, $item);
            #################   Create Request Item   ##################
                $this-> addRequestItem($quoteId, $itemId, $item);

            }
            return array('quote_name' => $incrementId, 'quote_id' => $quoteId);

    }



}


