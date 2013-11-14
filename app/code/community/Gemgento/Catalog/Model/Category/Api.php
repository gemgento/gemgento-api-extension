<?php
class Gemgento_Catalog_Model_Category_Api extends Mage_Catalog_Model_Category_Api
{

    /**
     * Initilize and return category model
     *
     * @param int $categoryId
     * @param string|int $store
     * @return Mage_Catalog_Model_Category
     */
    protected function _initCategory($categoryId, $store = null)
    {
        $category = Mage::getModel('catalog/category')
            ->setStoreId( ( $store == null ? $this->_getStoreId($store) : $store ) )
            ->load($categoryId);

        if (!$category->getId()) {
            $this->_fault('not_exists');
        }

        return $category;
    }


    /**
     * Retrieve list of assigned products to category
     *
     * @param int $categoryId
     * @param string|int $storeId
     * @return array
     */
    public function assignedProducts($categoryId, $storeId = 0)
    {
        $category = $this->_initCategory($categoryId, $storeId);
        $positions = $category->getProductsPosition();
        $collection = $category->setStoreId($storeId)->getProductCollection();
        $result = array();

        foreach ($collection as $product) {
            $result[] = array(
                'product_id' => $product->getId(),
                'type'       => $product->getTypeId(),
                'set'        => $product->getAttributeSetId(),
                'sku'        => $product->getSku(),
                'position'   => $positions[$product->getId()]
            );
        }

        return $result;
    }
    

} // Class Mage_Catalog_Model_Category_Api End
