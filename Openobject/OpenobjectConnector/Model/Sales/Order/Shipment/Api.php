<?php

/**
 *
 * @author     Mohammed NAHHAS
 * @package Openobject_OpenobjectConnector
 *
 */
class Openobject_OpenobjectConnector_Model_Sales_Order_Shipment_Api extends Mage_Sales_Model_Order_Shipment_Api {


    public function test($data) {

        $result = array();
        if(isset($data['imported'])) {

            $collection = Mage::getModel("sales/order")->getCollection()
                ->addAttributeToSelect('increment_id')
               	->addAttributeToFilter('imported', array('eq' => $data['imported']));

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
                $result[] =  $order['increment_id'];
            }

            return $result;
        }else{
            $this->_fault('data_invalid', "Error, the attribut 'imported' need to be specified");
        }
    }

}
