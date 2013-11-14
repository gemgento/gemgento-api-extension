<?php
/**
 * Catalog Product Mass Action processing model
 *
 * @category    Gemgento
 * @package     Gemgento_Catalog
 * @author      Gemgento Team
 */
class Gemgento_Catalog_Model_Product_Action extends Mage_Catalog_Model_Product_Action
{

    /**
     * Update attribute values for entity list per store
     *
     * @param array $productIds
     * @param array $attrData
     * @param int $storeId
     * @return Mage_Catalog_Model_Product_Action
     */
    public function updateAttributes($productIds, $attrData, $storeId)
    {
        Mage::dispatchEvent('catalog_product_attribute_update_before', array(
            'attributes_data' => &$attrData,
            'product_ids'   => &$productIds,
            'store_id'      => &$storeId
        ));

        $this->_getResource()->updateAttributes($productIds, $attrData, $storeId);
        $this->setData(array(
            'product_ids'       => array_unique($productIds),
            'attributes_data'   => $attrData,
            'store_id'          => $storeId
        ));

        $curr_date = date("Y-m-d H:i:s");

        foreach ($productIds as $productId) {
            $product = Mage::getModel('catalog/product')->load($productId);
            $productInfoData = $product->getData();
            $productInfoData['updated_at'] = $curr_date;
            $product->setData($productInfoData);
            $product->save();
        }

        // register mass action indexer event
        Mage::getSingleton('index/indexer')->processEntityAction(
            $this, Mage_Catalog_Model_Product::ENTITY, Mage_Index_Model_Event::TYPE_MASS_ACTION
        );
        return $this;
    }
}
