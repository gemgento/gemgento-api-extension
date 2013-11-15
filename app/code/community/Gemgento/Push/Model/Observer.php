<?php

class Gemgento_Push_Model_Observer {

    const URL = 'http://localhost:3000/gemgento/inventories/';

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
            'websites' => $product->getWebsiteIds()
        );

        foreach ($product->getTypeInstance(true)->getEditableAttributes($product) as $attribute) {
            $data[$attribute->getAttributeCode()] = $product->getData($attribute->getAttributeCode());
        }

        self::push('product', $data['product_id'], $data);

        print_r($data);
        exit;
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

        self::push('inventories', $data['product_id'], $data);

        print_r($data);
        exit;
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

        print_r($data);
        exit;
    }

    public function attribute_set($observer) {
        $attribute_set = $observer->getEvent()->getObject();

        $data = array(
            'set_id' => $attribute_set->getId(),
            'name' => $attribute_set->getAttributeSetName()
        );

        self::push('product_attribute_sets', $data);

        print_r($data);
        exit;
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

        $frontendLabels = array(
            array(
                'store_id' => 0,
                'label' => $model->getFrontendLabel()
            )
        );
        foreach ($model->getStoreLabels() as $store_id => $label) {
            $frontendLabels[] = array(
                'store_id' => $store_id,
                'label' => $label
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
            'frontend_label' => $frontendLabels
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

        // set options
        $options = $model->getAllOptions();
        // remove empty first element
        if ($model->getFrontendInput() != 'boolean') {
            array_shift($options);
        }

        if (count($options) > 0) {
            $data['options'] = $options;
        }

        self::push('product_attributes', $data['attribute_id'], $data);

        print_r($data);
        exit;
    }

    public function customer($observer) {
        $customer = $observer->getEvent()->getCustomer();
        $data = array();
        
        foreach ($customer->getAttributes() as $attribute) {
            $data[$attribute->getAttributeCode()] = $customer->getData($attribute->getAttributeCode());
        }

        self::push('product_attribute_sets', $data);

        print_r($data);
        die;
    }

    public function order($observer) {
        die(__METHOD__);
    }

    private function push($action, $id, $data) {
        $data_string = json_encode($data);

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

}
