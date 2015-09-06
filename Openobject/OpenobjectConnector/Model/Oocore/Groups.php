<?php
/**
*Openobject Magento Connector
*Generic API Extension for Magento Community/Enterprise Editions
*This connector is a reboot of the original Openlabs OpenERP Connector
*Copyright 2014 Kyle Waid
*Copyright 2009 Openlabs / Sharoon Thomas
*Some works Copyright by Mohammed NAHHAS
*/

class Openobject_OpenobjectConnector_Model_Oocore_Groups extends Mage_Catalog_Model_Api_Resource {
    public function items($filters=null) {
        try {
            $collection = Mage::getModel('core/store_group')->getCollection();//->addAttributeToSelect('*');
        }
        catch (Mage_Core_Exception $e) {
            $this->_fault('group_not_exists');
        }
            
        if (is_array($filters)) {
            try {
                foreach ($filters as $field => $value) {
                    $collection->addFieldToFilter($field, $value);
                }
            }

            catch (Mage_Core_Exception $e) {
                $this->_fault('filters_invalid', $e->getMessage());
                // If we are adding filter on non-existent attribute
            }
        }

        $result = array();
        foreach ($collection as $customer) {
            $result[] = $customer->toArray();
        }

        return $result;
    }


    public function info($groupIds = null) {
        $groups = array();

        if(is_array($groupIds)) {
            foreach($groupIds as $groupId) {
                try {
                    $groups[] = Mage::getModel('core/store_group')->load($groupId)->toArray();
                }

                catch (Mage_Core_Exception $e) {
                    $this->_fault('group_not_exists');
                }
            }
            return $groups;
        }

        elseif(is_numeric($groupIds)) {
            try {
                return Mage::getModel('core/store_group')->load($groupIds)->toArray();
            }
            catch (Mage_Core_Exception $e) {
                $this->_fault('group_not_exists');
            }

        }
        
    }

}
?>
