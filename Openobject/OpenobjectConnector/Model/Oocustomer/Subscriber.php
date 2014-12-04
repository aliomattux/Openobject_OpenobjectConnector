<?php
/**
*Openobject Magento Connector
*Generic API Extension for Magento Community/Enterprise Editions
*This connector is a reboot of the original Openlabs OpenERP Connector
*Copyright 2014 Kyle Waid
*Copyright 2009 Openlabs / Sharoon Thomas
*Some works Copyright by Mohammed NAHHAS
*/
class Openobject_OpenobjectConnector_Model_Oocustomer_Subscriber extends Mage_Customer_Model_Api_Resource
{
    protected $_mapAttributes = array(
        'customer_id' => 'entity_id'
    );

    public function __construct()
    {
        $this->_ignoredAttributeCodes[] = 'parent_id';
    }

    /**
     * Retrive subscriber list
     *
     * @param int $filters
     * @return array
     */
    public function items($filters=null)
    {
        $collection = Mage::getModel('customer/customer')->getCollection()
            ->addAttributeToSelect('*');


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
            $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($customer['email']);
            if($subscriber->getId())
                $result[] = $subscriber->getId();
        }

        return $result;
    }

    /**
     * Create new address for customer
     *
     * @param int $customerId
     * @param array $email
     * @return int
     */
    public function create($customerId,$email)
    {
        if($customerId && $email):
            $customer = Mage::getModel("newsletter/subscriber");

            $customer->setCustomerId($customerId);
            $customer->setEmail($email);
            $customer->subscriber_status = "1";

            $customer->save();

            return $customer->getId();
        else:
            return False;
        endif;
    }

    /**
     * Retrieve subscriber data
     *
     * @param int $subscriberId
     * @return array
     */
    public function info($subscriberId)
    {
        $subscriber = Mage::getModel('newsletter/subscriber')->load($subscriberId);

        if($subscriber->getId()):
                $result[] = $subscriber->toArray();
        endif;

        return $result;
    }

    /**
     * Update subscriber data (subscriber)
     *
     * @param $email
     * @return boolean
     */
    public function update($email)
    {
        if($email):
            $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);

            if($subscriber->getId()):
                $customer = Mage::getModel("newsletter/subscriber")->load($subscriber->getId());
                $customer->subscriber_status = "1";
                $customer->save();
            endif;

            return $subscriber->getId();
        else:
            return False;
        endif;
    }

    /**
     * Delete subscriber (unsubscriber)
     *
     * @param $email
     * @return boolean
     */
    public function delete($email)
    {
        if($email):
            $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);

            if($subscriber->getId()):
                Mage::getModel('newsletter/subscriber')->load($subscriber->getId())->unsubscribe();
            endif;

            return $subscriber->getId();
        else:
            return False;
        endif;
    }
} // Class Mage_Customer_Model_Address_Api End
