<?php

class Gemgento_Push_Model_Observer {

    var $_complexProductTypes = array('configurable', 'bundle', 'grouped');
    protected $_ignoredAttributeCodes = array(
        'global' => array('entity_id', 'attribute_set_id', 'entity_type_id')
    );

    public function __construct() {
        
    }
    
    /**
     * Send customer address data to Gemgento.
     * 
     * @param \Varien_Event_Observer $observer
     */
    public function address_save($observer) {
        
        if (!is_object(Mage::getSingleton('admin/session')->getUser())) { 
            return; # if event was not triggered by admin, stop here
        }
        
        $data = $observer->getEvent()->getCustomerAddress()->debug();
        self::push('PUT', 'addresses', $data['entity_id'], $data);
    }
    
    /**
     * Delete customer address data in Gemgento.
     * 
     * @param \Varien_Event_Observer $observer
     */
    public function address_delete($observer) {
        $data = $observer->getEvent()->getCustomerAddress()->debug();
        self::push('DELETE', 'addresses', $data['entity_id'], $data); 
    }

    /**
     * Send product data to Gemgento
     * 
     * @param \Varien_Event_Observer $observer
     */
    public function product_save($observer) {
        
        if (!is_object(Mage::getSingleton('admin/session')->getUser())) { 
            return; # if event was not triggered by admin, stop here
        }
        
        $product = $observer->getProduct();

        // Basic product data
        $data = array(
            'product_id' => $product->getId(),
            'gemgento_id' => $product->getGemgentoId(),
            'sku' => $product->getSku(),
            'set' => $product->getAttributeSetId(),
            'type' => $product->getTypeId(),
            'websites' => $product->getWebsiteIds(),
            'stores' => $product->getStoreIds(),
            'additional_attributes' => array(),
            'simple_product_ids' => array(),
            'configurable_product_ids' => Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($product->getId())
        );

        if ($product->getTypeId() == 'configurable') {
            $conf = Mage::getModel('catalog/product_type_configurable')->setProduct($product);
            $simple_collection = $conf->getUsedProductCollection()->addAttributeToSelect('*')->addFilterByRequiredOptions();
            foreach ($simple_collection as $simple_product) {
                $data['simple_product_ids'][] = $simple_product->getId();
            }
        }

        // load attribute values for each store
        foreach ($data['stores'] as $storeId) {
            $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($data['product_id']);
            $data['additional_attributes'][$storeId] = array();

            foreach ($product->getTypeInstance(true)->getEditableAttributes($product) as $attribute) {
                $data['additional_attributes'][$storeId][$attribute->getAttributeCode()] = $product->getData($attribute->getAttributeCode());
            }

            $data['additional_attributes'][$storeId]['category_ids'] = $product->getCategoryIds();

            if (isset($data['additional_attributes'][$storeId]['media_gallery']) && isset($data['additional_attributes'][$storeId]['media_gallery']['images'])) {

                # loop through each image
                foreach ($data['additional_attributes'][$storeId]['media_gallery']['images'] as $index => $image) {
                    $types = array();

                    # load the type(s) for each image
                    foreach ($product->getMediaAttributes() as $mediaAttribute) {
                        if ($product->getData($mediaAttribute->getAttributeCode()) == $image['file']) {
                            $types[] = $mediaAttribute->getAttributeCode();
                        }
                    }

                    #set the image types in the result array
                    $data['additional_attributes'][$storeId]['media_gallery']['images'][$index]['types'] = $types;
                }
            }
        }

        $id = $data['gemgento_id'];

        if ($id == NULL || $id == '') {
            $id = 0;
        }

        self::push('PUT', 'products', $id, $data);
    }

    /**
     * Delete product in Gemgento
     * 
     * @param \Varien_Event_Observer $observer
     */
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

    /**
     * Send stock data to Gemgento
     * 
     * @param \Varien_Event_Observer $observer
     */
    public function stock_save($observer) {
        $product_id = $observer->getEvent()->getItem()->getProductId();
        $product = Mage::getModel('catalog/product')->load($product_id);
        $data = array(
            'product_id' => $product_id,
            'inventories' => array()
        );

        $stock = array(); // stock data for all websites
        $stockCollection = Mage::getResourceModel('cataloginventory/stock_item_collection')->addProductsFilter(array($product))->load();
        $maxWebsite_id = 0;

        foreach ($stockCollection as $stockItem) {
            $tmpStock = $stockItem->getData();

            if ($maxWebsite_id < $tmpStock['website_id']) {
                $maxWebsite_id = $tmpStock['website_id'];
            }
            if (in_array($product->getTypeId(), $this->_complexProductTypes)) {
                $this->_filterComplexProductValues($tmpStock);
            }
            $stock[$tmpStock['website_id']] = $tmpStock;
        }

        foreach ($stock as $key => $value) {
            if (isset($values['website_id']) && ($value['website_id'] == $maxWebsite_id || empty($value['website_id']))) {
                unset($stock[$key]);
            }
        }

        $data['inventories'] = $stock;

        self::push('PUT', 'inventory', $data['product_id'], $data);
    }

    /**
     * Send category data to Gemgento
     * 
     * @param \Varien_Event_Observer $observer
     */
    public function category_save($observer) {
        $category = $observer->getEvent()->getCategory();

        // basic category data
        $data = array(
            'category_id' => $category->getId(),
            'is_active' => $category->getIsActive(),
            'position' => $category->getPosition(),
            'level' => $category->getLevel(),
            'store_ids' => $category->getStoreIds(),
            'products' => array()
        );

        // additional category attributes
        foreach ($category->getAttributes() as $attribute) {
            $data[$attribute->getAttributeCode()] = $category->getData($attribute->getAttributeCode());
        }

        // store specific product listings
        foreach ($data['store_ids'] as $storeId) {
            Mage::getModel('catalog/category')->setStoreId($storeId)->load($data['category_id']);
            $data['products']["0{$storeId}"] = array();
            $positions = $category->getProductsPosition();
            $collection = $category->getProductCollection();

            foreach ($collection as $product) {
                $data['products']["0{$storeId}"][] = array(
                    'product_id' => $product->getId(),
                    'position' => $positions[$product->getId()]
                );
            }
        }

        self::push('PUT', 'categories', $data['category_id'], $data);
    }

    /**
     * Delete category in Gemgento
     * 
     * @param \Varien_Event_Observer $observer
     */
    public function category_delete($observer) {
        $category = $observer->getEvent()->getCategory();

        self::push('DELETE', 'categories', $category->getId(), array());
    }
    
    /**
     * Change category position.
     * 
     * @param \Varien_Event_Observer $observer
     */
    public function category_move($observer) {
        $category = $observer->getEvent()->getCategory();

        // basic category data
        $data = array(
            'category_id' => $category->getId(),
            'is_active' => $category->getIsActive(),
            'position' => $category->getPosition(),
            'level' => $category->getLevel(),
            'store_ids' => $category->getStoreIds(),
            'products' => array()
        );

        // additional category attributes
        foreach ($category->getAttributes() as $attribute) {
            $data[$attribute->getAttributeCode()] = $category->getData($attribute->getAttributeCode());
        }
        
        self::push('PUT', 'categories', $data['category_id'], $data);
    }

    /**
     * Send attribute set data to Gemgento
     * 
     * @param \Varien_Event_Observer $observer
     */
    public function attribute_set_save($observer) {
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
    
    /**
     * Delete attribute set data in Gemgento
     * 
     * @param \Varien_Event_Observer $observer
     */
    public function attribute_set_delete($observer) {
       $attribute_set = $observer->getEvent()->getObject(); 
       
       self::push('DELETE', 'product_attribute_sets', $attribute_set->getId(), array());
    }

    /**
     * Send attribute data to Gemgento
     * 
     * @param \Varien_Event_Observer $observer
     */
    public function attribute_save($observer) {
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
    
    /**
     * Delete attribute in Gemgento
     * 
     * @param \Varien_Event_Observer $observer
     */
    public function attribute_delete($observer) {
       $attribute = $observer->getEvent()->getAttribute(); 
       
       self::push('DELETE', 'product_attributes', $attribute->getId(), array());
    }

    /**
     * Send customer data to Gemgento
     * 
     * @param \Varien_Event_Observer $observer
     */
    public function customer_save($observer) {
        
        if (!is_object(Mage::getSingleton('admin/session')->getUser())) { 
            return; # if event was not triggered by admin, stop here
        }
        
        $customer = $observer->getEvent()->getCustomer();
        $data = array();

        foreach ($customer->getAttributes() as $attribute) {
            $data[$attribute->getAttributeCode()] = $customer->getData($attribute->getAttributeCode());
        }

        self::push('PUT', 'users', $data['entity_id'], $data);
    }
    
    /**
     * Delete customer in Gemgento
     * 
     * @param \Varien_Event_Observer $observer
     */
    public function customer_delete($observer) {
        $customer = $observer->getEvent()->getCustomer();

        self::push('DELETE', 'users', $customer->getId(), array());
    }

    /**
     * Send order data to Gemgento
     * 
     * @param \Varien_Event_Observer $observer
     */
    public function order_save($observer) {
        $order = $observer->getEvent()->getOrder();
        $data = array();

        $data = $this->_getAttributes($order, 'order');

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

        $id = $data['gemgento_id'];

        if ($id == NULL || $id == '') {
            $id = 0;
        }

        self::push('PUT', 'orders', $id, $data);
    }

    /**
     * Send store data to Gemgento
     * 
     * @param \Varien_Event_Observer $observer
     */
    public function store_save($observer) {
        $store = $observer->getEvent()->getStore();

        $data = array();
        $data['store_id'] = $store->getId();
        $data['code'] = $store->getCode();
        $data['website_id'] = $store->getWebsiteId();
        $data['group_id'] = $store->getGroupId();
        $data['name'] = $store->getName();
        $data['sort_order'] = $store->getSortOrder();
        $data['is_active'] = $store->getIsActive();

        self::push('PUT', 'stores', $data['store_id'], $data);
    }

    /**
     * Send request to Gemgento
     * 
     * @param string $action HTTP verb
     * @param string $path the Gemgento URL relative path
     * @param integer $id ID of the model
     * @param array $data paramters to send
     */
    private function push($action, $path, $id, $data) {
        $data_string = json_encode(Array('data' => $data));
        $parts = parse_url($this->gemgento_url() . $path . '/' . $id);

        $fp = fsockopen($parts['host'], isset($parts['port']) ? $parts['port'] : 80, $errno, $errstr, 30);

        $out = "$action " . $parts['path'] . " HTTP/1.1\r\n";
        $out.= "Host: " . $parts['host'] . "\r\n";

        if ($this->gemgento_user() !== NULL && $this->gemgento_password() !== NULL) {
            $out.= "Authorization: Basic " . base64_encode($this->gemgento_user() . ":" . $this->gemgento_password()) . "\r\n";
        }

        $out.= "Content-Type: application/json\r\n";
        $out.= "Content-Length: " . strlen($data_string) . "\r\n";
        $out.= "Connection: Close\r\n\r\n";
        $out.= $data_string;

        fwrite($fp, $out);
        fclose($fp);
    }

    /**
     * Get the Gemgento URL from configuration
     * 
     * @return string
     */
    private function gemgento_url() {
        $url = Mage::getStoreConfig("gemgento_push/settings/gemgento_url");

        if (substr($url, -1) != '/') {
            $url .= '/';
        }

        return $url;
    }

    /**
     * Get the Gemgento HTTP auth user from configuration
     * 
     * @return string
     */
    private function gemgento_user() {
        $user = Mage::getStoreConfig("gemgento_push/settings/gemgento_user");

        if ($user === NULL || $user == '') {
            return null;
        } else {
            return $user;
        }
    }

    /**
     * Get the Gemgento HTTP auth password from configuration
     * 
     * @return string
     */
    private function gemgento_password() {
        $user = Mage::getStoreConfig("gemgento_push/settings/gemgento_password");

        if ($user === NULL || $user == '') {
            return null;
        } else {
            return $user;
        }
    }

    private function _filterComplexProductValues(&$productData) {
        $validKeys = array(
            'item_id',
            'website_id',
            'product_id',
            'stock_id',
            'manage_stock',
            'use_config_manage_stock',
            'enable_qty_increments',
            'use_config_enable_qty_increments',
            'qty_increments',
            'use_config_qty_increments',
            'stock_availability',
            'is_in_stock',
        );
        foreach ($productData as $key => $value) {
            if (!in_array($key, $validKeys)) {
                unset($productData[$key]);
            }
        }
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
