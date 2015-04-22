<?php

class Gemgento_Push_Helper_Catalog_Product extends Mage_Core_Helper_Abstract
{

    public function export($product)
    {
        // Basic product data
        $data = array(
            'product_id' => $product->getId(),
            'gemgento_id' => $product->getGemgentoId(),
            'sku' => $product->getSku(),
            'set' => $product->getAttributeSetId(),
            'type' => $product->getTypeId(),
            'websites' => $product->getWebsiteIds(),
            'stores' => $product->getStoreIds(),
            'additional_attributes' => $this->additionalAttributes($product, $product->getStoreIds()),
            'simple_product_ids' => $this->simpleProductIds($product),
            'configurable_product_ids' => Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($product->getId()),
            'bundle_options' => $this->bundleOptions($product)
        );

        return $data;
    }

    public function simpleProductIds($product)
    {
        $ids = array();

        if ($product->getTypeId() == 'configurable') {
            $conf = Mage::getModel('catalog/product_type_configurable')->setProduct($product);
            $simple_collection = $conf->getUsedProductCollection()->addAttributeToSelect('*')->addFilterByRequiredOptions();
            foreach ($simple_collection as $simple_product) {
                $ids[] = $simple_product->getId();
            }
        }

        return $ids;
    }

    public function additionalAttributes($product, $storeIds)
    {
        $additionalAttributes = array();

        foreach ($storeIds as $storeId) {
            $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($product->getId());
            $additionalAttributes[$storeId] = array();

            foreach ($product->getTypeInstance(true)->getEditableAttributes($product) as $attribute) {
                $additionalAttributes[$storeId][$attribute->getAttributeCode()] = $product->getData($attribute->getAttributeCode());
            }

            $additionalAttributes[$storeId]['category_ids'] = $product->getCategoryIds();

            if (isset($additionalAttributes[$storeId]['media_gallery']) && isset($additionalAttributes[$storeId]['media_gallery']['images'])) {

                # loop through each image
                foreach ($additionalAttributes[$storeId]['media_gallery']['images'] as $index => $image) {
                    $types = array();

                    # load the type(s) for each image
                    foreach ($product->getMediaAttributes() as $mediaAttribute) {
                        if ($product->getData($mediaAttribute->getAttributeCode()) == $image['file']) {
                            $types[] = $mediaAttribute->getAttributeCode();
                        }
                    }

                    #set the image types in the result array
                    $additionalAttributes[$storeId]['media_gallery']['images'][$index]['types'] = $types;
                }
            }
        }

        return $additionalAttributes;
    }

    public function bundleOptions($product)
    {
        $bundleOptions = array();

        if($product->getTypeId() == 'bundle')
        {

            foreach( $product->getTypeInstance()->getOptions() as $option )
            {
                $bundleOptions[] = array(
                    'id' => $option->getId(),
                    'required' => $option->getRequired(),
                    'position' => $option->getPosition(),
                    'type' => $option->getType(),
                    'default_title' => $option->getDefaultTitle(),
                    'selections' => $this->bundleOptionSelections($product, $option->getId())
                );
            }
        }

        return $bundleOptions;
    }

    public function bundleOptionSelections($product, $optionId)
    {
        $optionSelections = array();

        $selections = $product->getTypeInstance(true)->getSelectionsCollection($optionId, $product);

        foreach( $selections as $selection )
        {
            $optionSelections[] = array(
                'id' => $selection->getId(),
                'product_id' => $selection->getProductId(),
                'price_type' => $selection->getSelectionPriceType(),
                'price_value' => $selection->getSelectionPriceValue(),
                'qty' => $selection->getSelectionQty(),
                'can_change_qty' => $selection->getSelectionCanChangeQty(),
                'position' => $selection->getPosition(),
                'is_default' => $selection->getIsDefault()
            );
        }

        return $optionSelections;
    }

}