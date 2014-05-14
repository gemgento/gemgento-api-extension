<?php

class Bubble_Api_Model_Catalog_Product_Api extends Mage_Catalog_Model_Product_Api
{
    
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
    
    
    public function create($type, $set, $sku, $productData, $store = null)
    {
        // Allow attribute set name instead of id
        if (is_string($set) && !is_numeric($set)) {
            $set = Mage::helper('bubble_api')->getAttributeSetIdByName($set);
        }

        return parent::create($type, $set, $sku, $productData, $store);
    }

    
    protected function _prepareDataForSave($product, $productData)
    {
        /* @var $product Mage_Catalog_Model_Product */

        if (isset($productData['categories'])) {
            $categoryIds = Mage::helper('bubble_api/catalog_product')
                ->getCategoryIdsByNames((array) $productData['categories']);
            if (!empty($categoryIds)) {
                $productData['categories'] = array_unique($categoryIds);
            }
        }

        if (isset($productData['website_ids'])) {
            $websiteIds = $productData['website_ids'];
            foreach ($websiteIds as $i => $websiteId) {
                if (!is_numeric($websiteId)) {
                    $website = Mage::app()->getWebsite($websiteId);
                    if ($website->getId()) {
                        $websiteIds[$i] = $website->getId();
                    }
                }
            }
            $product->setWebsiteIds($websiteIds);
            unset($productData['website_ids']);
        }

        foreach ($productData as $code => $value) {
            $productData[$code] = Mage::helper('bubble_api/catalog_product')
                ->getOptionKeyByLabel($code, $value);
        }

        parent::_prepareDataForSave($product, $productData);

        if (isset($productData['associated_skus'])) {
            $simpleSkus = $productData['associated_skus'];
            $priceChanges = isset($productData['price_changes']) ? $productData['price_changes'] : array();
            $configurableAttributes = isset($productData['configurable_attributes']) ? $productData['configurable_attributes'] : array();
            Mage::helper('bubble_api/catalog_product')->associateProducts($product, $simpleSkus, $priceChanges, $configurableAttributes);
        }
    }
}