<?php
/**
*Openobject Magento Connector
*Generic API Extension for Magento Community/Enterprise Editions
*This connector is a reboot of the original Openlabs OpenERP Connector
*Copyright 2014 Kyle Waid
*Copyright 2009 Openlabs / Sharoon Thomas
*Some works Copyright by Mohammed NAHHAS
*/
class Openobject_OpenobjectConnector_Model_Oocustomer_Customer extends Mage_Catalog_Model_Api_Resource
{

        protected $_mapFilters = array(
            'customer_id' => 'entity_id'
        );

        /**
         * Return the list of partner ids which match the filters
         *
         * @param array $filters
         * @return array
         */

    public function search($filters = null) {
        /*Search for product ids based on filters
        *This function is not good because it iterates over every result, which is very slow
        *TODO: Improve efficiency */

        $collection = Mage::getModel('customer/customer')->getCollection()
          //  ->setStoreId($this->_getStoreId($store))
            ->addAttributeToSelect('entity_id');

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

    public function multinfo($customerIds) {

        $collection = Mage::getModel('customer/customer')
                ->getCollection()
                ->addAttributeToFilter('entity_id', array('in' => $customerIds))
                ->addAttributeToSelect('*');

        $result = array();

        foreach ($collection as $collection_item) {
            $coll_array = $collection_item->toArray();
            $addresses = array();

            $this->_dbi = Mage::getSingleton('core/resource') ->getConnection('core_read');
            $query = "SELECT entity_id FROM customer_address_entity WHERE parent_id = {$coll_array['entity_id']}";
            $addressIds = $this->_dbi->fetchCol($query);
            $addressesCollection = Mage::getResourceModel('customer/address_collection')
               // ->getCollection()
                ->addFieldToFilter('entity_id', array('in' => $addressIds))
                ->addAttributeToSelect('*');

            foreach ($addressesCollection as $address) {
                $addresses[] = $address->toArray();

            }
            $coll_array['test_id'] = $coll_array['entity_id'];
            $coll_array['addresses'] = $addresses;
            $result[] = $coll_array;
        }

        return $result;
    }

        public function items($filters=null)
        {
            try
            {
            $collection = Mage::getModel('customer/customer')->getCollection();//->addAttributeToSelect('*');
            }
            catch (Mage_Core_Exception $e)
            {
               $this->_fault('customer_not_exists');
            }
            
            if (is_array($filters)) {
                try {
                    foreach ($filters as $field => $value) {
                        $collection->addFieldToFilter($field, $value);
                    }
                } catch (Mage_Core_Exception $e) {
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

    public function info($groupIds = null)
    {
        $groups = array();

        if(is_array($groupIds))
        {
            foreach($groupIds as $groupId)
            {
                try
                                {
                                    $groups[] = Mage::getModel('customer')->load($groupId)->toArray();
                }
                                catch (Mage_Core_Exception $e)
                                {
                                    $this->_fault('customer_not_exists');
                                }
                        }
                        return $groups;
        }
                elseif(is_numeric($groupIds))
        {
            try
                        {
                            return Mage::getModel('customer')->load($groupIds)->toArray();
            }
                        catch (Mage_Core_Exception $e)
                        {
                            $this->_fault('customer_not_exists');
                        }

                }
        
        }

        public function create($groupdata)
        {
            try
            {
                $group = Mage::getModel('customer')
                    ->setData($groupdata)
                    ->save();

            }
            catch (Magento_Core_Exception $e)
            {
                $this->_fault('data_invalid',$e->getMessage());
            }
            catch (Exception $e)
            {
                $this->_fault('data_invalid',$e->getMessage());
            }
            return $group->getId();
        }

        public function update($groupid,$groupdata)
        {
            try
            {
                $group = Mage::getModel('customer')
                    ->load($groupid);
                if (!$group->getId())
                {
                    $this->_fault('customer_not_exists');
                }
                $group->addData($groupdata)->save();
            }
            catch (Magento_Core_Exception $e)
            {
                $this->_fault('data_invalid',$e->getMessage());
            }
            catch (Exception $e)
            {
                $this->_fault('data_invalid',$e->getMessage());
            }
            return true;
        }

        public function delete($groupid)
        {
            try
            {
                $group = Mage::getModel('customer')
                    ->load($groupid);
                if (!$group->getId())
                {
                    $this->_fault('customer_not_exists');
                }
                $group->delete();

            }
            catch (Magento_Core_Exception $e)
            {
                $this->_fault('data_invalid',$e->getMessage());
            }
            catch (Exception $e)
            {
                $this->_fault('data_invalid',$e->getMessage());
            }
            return true;
        }
}
?>
