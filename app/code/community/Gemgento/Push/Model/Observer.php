<?php

class Gemgento_Push_Model_Observer {

    public function __construct() {
        
    }

    public function product_save($observer) {
        $product = $observer->getProduct();

        $data = array(// Basic product data
            'product_id' => $product->getId(),
            'gemgento_id' => $product->getGemgentoId(),
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

        $id = $data['gemgento_id'];

        if ($id == NULL || $id == '') {
            $id = 0;
        }

        self::push('PUT', 'products', $id, $data);
    }

    public function product_delete($observer) {
        $product = $observer->getProduct();

        $data = array(
            'product_id' => $product->getId(),
            'gemgento_id' => $product->getGemgentoId()
        );

        $id = $data['gemgento_id'];

        if ($id == NULL || $id == '') {
            $id = 0;
        }

        self::push('DELETE', 'products', $id, $data);
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

        self::push('PUT', 'inventory', $data['product_id'], $data);
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

        self::push('PUT', 'categories', $data['category_id'], $data);
    }

    public function attribute_set($observer) {
        $attribute_set = $observer->getEvent()->getObject();
        $attributes = Mage::getModel('catalog/product')->getResource()
                ->loadAllAttributes()
                ->getSortedAttributes($attribute_set->getId());

        $data = array(
            'set_id' => $attribute_set->getId(),
            'name' => $attribute_set->getAttributeSetName(),
            'attributes' => array()
        );

        foreach ($attributes as $attribute) {
            $data['attributes'][] = $attribute->getAttributeId();
        }

        self::push('PUT', 'product_attribute_sets', $data['set_id'], $data);
    }

    public function attribute($observer) {
        $model = $observer->getEvent()->getAttribute();

        if ($model->getAttributeCode() === NULL) {
            return NULL;
        }

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

            $store_options = $model->setStoreId($store_id)->getSource()->getAllOptions();

            if (sizeof($store_options) == 1 && $store_options[0]['label'] === '') {
                $store_options = array();
            }

            $options[] = array(
                'store_id' => $store_id,
                'options' => $store_options
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

        self::push('PUT', 'product_attributes', $data['attribute_id'], $data);
    }

    public function customer($observer) {
        $customer = $observer->getEvent()->getCustomer();
        $data = array();

        foreach ($customer->getAttributes() as $attribute) {
            $data[$attribute->getAttributeCode()] = $customer->getData($attribute->getAttributeCode());
        }

        self::push('PUT', 'users', $data['entity_id'], $data);
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

        self::push('PUT', 'orders', $data['gemgento_id'], $data);
    }

    private function push($action, $path, $id, $data) {
        $data_string = json_encode(Array('data' => $data));
        $parts = parse_url($this->gemgento_url() . $path . '/' . $id);

        $fp = fsockopen($parts['host'], isset($parts['port']) ? $parts['port'] : 80, $errno, $errstr, 30);

        $out = "$action " . $parts['path'] . " HTTP/1.1\r\n";
        $out.= "Host: " . $parts['host'] . "\r\n";
        $out.= "Content-Type: application/json\r\n";
        $out.= "Content-Length: " . strlen($data_string) . "\r\n";
        $out.= "Connection: Close\r\n\r\n";
        $out.= $data_string;

        fwrite($fp, $out);
        fclose($fp);
    }

    private function gemgento_url() {
        $url = Mage::getStoreConfig("gemgento_push/settings/gemgento_url");

        if (substr($url, -1) != '/') {
            $url .= '/';
        }

        return $url;
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
