<?php

class Gemgento_Catalog_Model_Product_Api extends Mage_Catalog_Model_Product_Api {

    /**
     * Retrieve product info
     *
     * @param int|string $productId
     * @param string|int $store
     * @param array $attributes
     * @return array
     */
    public function info($productId, $store = null, $attributes = null, $identifierType = null) {
        $product = $this->_getProduct($productId, $store, $identifierType);

        $result = array(// Basic product data
            'product_id' => $product->getId(),
            'sku' => $product->getSku(),
            'set' => $product->getAttributeSetId(),
            'type' => $product->getTypeId(),
            'categories' => $product->getCategoryIds(),
            'websites' => $product->getWebsiteIds(),
            'configurable_product_ids' => Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($product->getId())
        );

        // Add id's of associated simple products
        if ($product->getTypeId() == 'configurable') {
            $conf = Mage::getModel('catalog/product_type_configurable')->setProduct($product);
            $simple_collection = $conf->getUsedProductCollection()->addAttributeToSelect('*')->addFilterByRequiredOptions();
            foreach ($simple_collection as $simple_product) {
                $result['simple_product_ids'][] = $simple_product->getId();
            }
        }

        foreach ($product->getTypeInstance(true)->getEditableAttributes($product) as $attribute) {
            if ($this->_isAllowedAttribute($attribute, $attributes)) {
                $result[$attribute->getAttributeCode()] = $product->getData(
                        $attribute->getAttributeCode());
            }
        }

        return $result;
    }

}
