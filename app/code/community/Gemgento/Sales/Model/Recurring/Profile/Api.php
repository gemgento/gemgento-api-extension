<?php

class Gemgento_Sales_Model_Recurring_Profile_Api extends Mage_Sales_Model_Api_Resource {

    /**
     * @return array
     */
    public function items() {
        $profiles = array();
        $profileCollection = Mage::getModel('sales/recurring_profile')->getCollection();

        foreach ($profileCollection as $profile) {
            $data = $this->_getAttributes($profile, 'recurring_profile');
            $data['order_ids'] = $profile->getChildOrderIds();
            $profiles[] = $data;
        }

        $profile->save();

        return $profiles;
    }

    /**
     * @param int $profileId
     * @param string $state
     * @return bool
     */
    public function updateState($profileId, $state) {
        $profile = Mage::getModel('sales/recurring_profile')->load($profileId);

        switch ($state) {
            case 'cancel':
                $profile->cancel();
                break;
            case 'suspend':
                $profile->suspend();
                break;
            case 'activate':
                $profile->activate();
                break;
        }
        
        return true;
    }

    /**
     * Retrieve entity attributes values
     *
     * @param Mage_Core_Model_Abstract $object
     * @param string $type
     * @param array $attributes
     * @return Mage_Sales_Model_Api_Resource
     */
    protected function _getAttributes($object, $type, array $attributes = null) {
        $result = array();

        if (!is_object($object)) {
            return $result;
        }

        foreach ($object->getData() as $attribute => $value) {
            if ($this->_isAllowedAttribute($attribute, $type, $attributes)) {
                $result[$attribute] = $value;
            }
        }

        if (isset($this->_attributesMap['global'])) {
            foreach ($this->_attributesMap['global'] as $alias => $attributeCode) {
                $result[$alias] = $object->getData($attributeCode);
            }
        }

        if (isset($this->_attributesMap[$type])) {
            foreach ($this->_attributesMap[$type] as $alias => $attributeCode) {
                $result[$alias] = $object->getData($attributeCode);
            }
        }

        return $result;
    }

    /**
     * Check is attribute allowed to usage
     *
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attributeCode
     * @param string $type
     * @param array $attributes
     * @return boolean
     */
    protected function _isAllowedAttribute($attributeCode, $type, array $attributes = null) {
        if (!empty($attributes) && !(in_array($attributeCode, $attributes))) {
            return false;
        }

        if (in_array($attributeCode, $this->_ignoredAttributeCodes['global'])) {
            return false;
        }

        if (isset($this->_ignoredAttributeCodes[$type]) && in_array($attributeCode, $this->_ignoredAttributeCodes[$type])) {
            return false;
        }

        return true;
    }
}
