<?php

class Gemgento_Push_Helper_Catalog_Attribute extends Mage_Core_Helper_Abstract
{

    public function export($attribute)
    {
        // Basic product data
        $data = array(
            'attribute_id' => $attribute->getId(),
            'attribute_code' => $attribute->getAttributeCode(),
            'frontend_input' => $attribute->getFrontendInput(),
            'default_value' => $attribute->getDefaultValue(),
            'is_unique' => $attribute->getIsUnique(),
            'is_required' => $attribute->getIsRequired(),
            'apply_to' => $attribute->getApplyTo(),
            'is_configurable' => $attribute->getIsConfigurable(),
            'is_searchable' => $attribute->getIsSearchable(),
            'is_visible_in_advanced_search' => $attribute->getIsVisibleInAdvancedSearch(),
            'is_comparable' => $attribute->getIsComparable(),
            'is_used_for_promo_rules' => $attribute->getIsUsedForPromoRules(),
            'is_visible_on_front' => $attribute->getIsVisibleOnFront(),
            'used_in_product_listing' => $attribute->getUsedInProductListing(),
            'scope' => $this->scope($attribute),
            'frontend_label' => $this->frontendLabels($attribute),
            'options' => $this->options($attribute),
            'additional_fields' => $this->additionalFields($attribute)
        );

        return $data;
    }

    public function scope($attribute)
    {
        switch ($attribute->getIsGlobal()) {

            case 1:
                return 'global';

            case 2:
                return 'website';

            case 0:
                return 'store';

            default:
                return 'store';
        }
    }


    public function frontendLabels($attribute)
    {
        $frontendLabels = array();

        foreach ($attribute->getStoreLabels() as $store_id => $label) {
            $frontendLabels[] = array(
                'store_id' => $store_id,
                'label' => $label
            );
        }

        return $frontendLabels;
    }

    public function options($attribute)
    {
        $options = array();

        foreach ($attribute->getStoreLabels() as $store_id => $label) {
            $store_options = $attribute->setStoreId($store_id)->getSource()->getAllOptions();

            if (sizeof($store_options) == 1 && $store_options[0]['label'] === '') {
                $store_options = array();
            }

            $options[] = array(
                'store_id' => $store_id,
                'options' => $store_options
            );
        }

        return $options;
    }

    public function additionalFields($attribute) {
        // set additional fields to different types
        switch ($attribute->getFrontendInput()) {

            case 'text':
                return array(
                    'frontend_class' => $attribute->getFrontendClass(),
                    'is_html_allowed_on_front' => $attribute->getIsHtmlAllowedOnFront(),
                    'used_for_sort_by' => $attribute->getUsedForSortBy()
                );
                break;

            case 'textarea':
                return array(
                    'is_wysiwyg_enabled' => $attribute->getIsWysiwygEnabled(),
                    'is_html_allowed_on_front' => $attribute->getIsHtmlAllowedOnFront(),
                );
                break;

            case 'boolean':
                return array(
                    'used_for_sort_by' => $attribute->getUsedForSortBy()
                );
                break;

            case 'multiselect':
                return array(
                    'is_filterable' => $attribute->getIsFilterable(),
                    'is_filterable_in_search' => $attribute->getIsFilterableInSearch(),
                    'position' => $attribute->getPosition()
                );
                break;

            case 'price':
                return array(
                    'is_filterable' => $attribute->getIsFilterable(),
                    'is_filterable_in_search' => $attribute->getIsFilterableInSearch(),
                    'position' => $attribute->getPosition(),
                    'used_for_sort_by' => $attribute->getUsedForSortBy()
                );
                break;

            default:
                return array();
                break;
        }
    }

}