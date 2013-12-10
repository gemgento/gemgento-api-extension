<?php

class Gemgento_Push_Model_Observer {

    const URL = 'http://localhost:3000/';

    public function __construct() {
        
    }

    public function product($observer) {
        $product = $observer->getProduct();

        $data = array(// Basic product data
            'product_id' => $product->getId(),
            'sku' => $product->getSku(),
            'set' => $product->getAttributeSetId(),
            'type' => $product->getTypeId(),
            'categories' => $product->getCategoryIds(),
            'websites' => $product->getWebsiteIds(),
            'stores' => $product->getStoreIds(),
            'additional_attributes' => array()
        );

        foreach ($product->getTypeInstance(true)->getEditableAttributes($product) as $attribute) {
            $data['additional_attributes'][$attribute->getAttributeCode()] = $product->getData($attribute->getAttributeCode());
        }

        self::push('products', $data['product_id'], $data);
    }

    public function stock($observer) {
        $stock_item = $observer->getEvent()->getItem();
        $product = $stock_item->getProduct();

        $data = array(
            'product_id' => $product->getId(),
            'sku' => $product->getSku(),
            'qty' => $stock_item->getQty(),
            'is_in_stock' => $stock_item->getIsInStock()
        );

        self::push('inventory', $data['product_id'], $data);
    }

    public function category($observer) {
        $category = $observer->getEvent()->getCategory();

        $data = array(
            'category_id' => $category->getId(),
            'is_active' => $category->getIsActive(),
            'position' => $category->getPosition(),
            'level' => $category->getLevel()
        );

        foreach ($category->getAttributes() as $attribute) {
            $data[$attribute->getAttributeCode()] = $category->getData($attribute->getAttributeCode());
        }

        self::push('categories', $data['category_id'], $data);
    }

    public function attribute_set($observer) {
        $attribute_set = $observer->getEvent()->getObject();

        $data = array(
            'set_id' => $attribute_set->getId(),
            'name' => $attribute_set->getAttributeSetName()
        );

        self::push('product_attribute_sets', $data['set_id'], $data);
    }

    public function attribute($observer) {
        $model = $observer->getEvent()->getAttribute();

        if ($model->isScopeGlobal()) {
            $scope = 'global';
        } elseif ($model->isScopeWebsite()) {
            $scope = 'website';
        } else {
            $scope = 'store';
        }

        $frontendLabels = array();
        $options = array();
        
        foreach ($model->getStoreLabels() as $store_id => $label) {
            $frontendLabels[] = array(
                'store_id' => $store_id,
                'label' => $label
            );
            
            $options[] = array(
                'store_id' => $store_id,
                'options' => $model->setStoreId($store_id)->getSource()->getAllOptions()
            );
        }

        $data = array(
            'attribute_id' => $model->getId(),
            'attribute_code' => $model->getAttributeCode(),
            'frontend_input' => $model->getFrontendInput(),
            'default_value' => $model->getDefaultValue(),
            'is_unique' => $model->getIsUnique(),
            'is_required' => $model->getIsRequired(),
            'apply_to' => $model->getApplyTo(),
            'is_configurable' => $model->getIsConfigurable(),
            'is_searchable' => $model->getIsSearchable(),
            'is_visible_in_advanced_search' => $model->getIsVisibleInAdvancedSearch(),
            'is_comparable' => $model->getIsComparable(),
            'is_used_for_promo_rules' => $model->getIsUsedForPromoRules(),
            'is_visible_on_front' => $model->getIsVisibleOnFront(),
            'used_in_product_listing' => $model->getUsedInProductListing(),
            'frontend_label' => $frontendLabels,
            'options' => $options
        );
        
        if ($model->getFrontendInput() != 'price') {
            $data['scope'] = $scope;
        }

        // set additional fields to different types
        switch ($model->getFrontendInput()) {
            case 'text':
                $data['additional_fields'] = array(
                    'frontend_class' => $model->getFrontendClass(),
                    'is_html_allowed_on_front' => $model->getIsHtmlAllowedOnFront(),
                    'used_for_sort_by' => $model->getUsedForSortBy()
                );
                break;
            case 'textarea':
                $data['additional_fields'] = array(
                    'is_wysiwyg_enabled' => $model->getIsWysiwygEnabled(),
                    'is_html_allowed_on_front' => $model->getIsHtmlAllowedOnFront(),
                );
                break;
            case 'date':
            case 'boolean':
                $data['additional_fields'] = array(
                    'used_for_sort_by' => $model->getUsedForSortBy()
                );
                break;
            case 'multiselect':
                $data['additional_fields'] = array(
                    'is_filterable' => $model->getIsFilterable(),
                    'is_filterable_in_search' => $model->getIsFilterableInSearch(),
                    'position' => $model->getPosition()
                );
                break;
            case 'select':
            case 'price':
                $data['additional_fields'] = array(
                    'is_filterable' => $model->getIsFilterable(),
                    'is_filterable_in_search' => $model->getIsFilterableInSearch(),
                    'position' => $model->getPosition(),
                    'used_for_sort_by' => $model->getUsedForSortBy()
                );
                break;
            default:
                $data['additional_fields'] = array();
                break;
        }

        self::push('product_attributes', $data['attribute_id'], $data);
    }

    public function customer($observer) {
        $customer = $observer->getEvent()->getCustomer();
        $data = array();

        foreach ($customer->getAttributes() as $attribute) {
            $data[$attribute->getAttributeCode()] = $customer->getData($attribute->getAttributeCode());
        }

        self::push('users', $data['entity_id'], $data);
    }

    public function order($observer) {
        $order = $observer->getEvent()->getOrder();
        $data = array();

        $data['order_id'] = $order->getId();
        $data['gemgento_id'] = $order->getGemgentoId();
        $data['store_id'] = $order->getStoreId();
        $data['shipping_address'] = $this->_getAttributes($order->getShippingAddress(), 'order_address');
        $data['billing_address'] = $this->_getAttributes($order->getBillingAddress(), 'order_address');
        $data['items'] = array();

        foreach ($order->getAllItems() as $item) {
            if ($item->getGiftMessageId() > 0) {
                $item->setGiftMessage(
                        Mage::getSingleton('giftmessage/message')->load($item->getGiftMessageId())->getMessage()
                );
            }

            $data['items'][] = $this->_getAttributes($item, 'order_item');
        }

//        $data['payment'] = $this->_getAttributes($order->getPayment(), 'order_payment');

        $data['status_history'] = array();

        foreach ($order->getAllStatusHistory() as $history) {
            $data['status_history'][] = $this->_getAttributes($history, 'order_status_history');
        }

        self::push('orders', $data['order_id'], $data);
    }

    private function push($action, $id, $data) {
        $data_string = json_encode(Array('data' => $data));

        $ch = curl_init(self::URL . $action . '/' . $id);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string))
        );

        $result = curl_exec($ch);

        return $result;
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
