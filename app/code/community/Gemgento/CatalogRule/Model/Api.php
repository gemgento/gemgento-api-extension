<?php

class Gemgento_CatalogRule_Model_Api extends Mage_Catalog_Model_Api_Resource {

    public function items() {
        $catalogrules = array();
        $collection = Mage::getModel('catalogrule/rule')->getResourceCollection();
        $collection->addWebsitesToResult();
        
        foreach ($collection as $rule) {
            $data = $this->_getAttributes($rule, 'rule');
            unset($data['actions_serialized']);
            unset($data['conditions_serialized']);
            $data['conditions'] = unserialize($rule->getConditionsSerialized());
            $catalogrules[] = $data;
        }

        return $catalogrules;
    }

    /**
     * Retrieve entity attributes values
     *
     * @param Mage_Core_Model_Abstract $object
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
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attribute
     * @param string $entityType
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

?>
