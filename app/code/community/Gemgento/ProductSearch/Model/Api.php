<?php

class Gemgento_ProductSearch_Model_Api extends Mage_Catalog_Model_Api_Resource {

    public function results($query, $limit = null, $page = 0) {
        $result = array();

        if ($limit !== null && !is_numeric($limit)) {
            $this->_fault('invalid_limit', 'The supplied limit for the search results is invalid!');
            return $result;
        } elseif (!is_numeric($page)) {
            $this->_fault('invalid_page', 'The supplied page for the search results is invalid!');
            return $result;
        }

        $productList = $this->_performSearch($query, $limit, $page);

        if ($productList) {
            foreach ($productList as $product) {
                $result[] = $product->getId();
            }
        }

        return $result;
    }

    private function _performSearch($query, $limit = null, $page = 0) {
        $currentStoreId = Mage::app()->getStore()->getId();
        // Use default Store if Store has not been set already
        if (!$currentStoreId) {
            Mage::app()->getStore()->setId(Mage::app()->getDefaultStoreView()->getStoreId());
        }

        //Set the search parameter
        Mage::helper('productSearch')->getRequest()->setParam('q', $query);

        //Save or get the query data from the database
        Mage::helper('productSearch')->gemgentoCustomSearchInit();

        // Preform the search
        $productList = Mage::getModel('catalogsearch/fulltext')->getCollection()
                ->addAttributeToSelect('*')
                ->addSearchFilter(Mage::helper('catalogsearch')->getQuery()->getQueryText())
                ->addStoreFilter()
                ->addUrlRewrite()
                ->addFieldToFilter('visibility', array(
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH, // visible both in search and in catalog...
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH // visible in search...
                ))
                ->setPage($page, $limit);

        // Sort result set by relevance if possible
        $currentSearchType = Mage::getStoreConfig('catalog/search/search_type', $currentStoreId);
        if ($currentSearchType && ($currentSearchType == Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_FULLTEXT || $currentSearchType == Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_COMBINE)) {
            $productList->getSelect()->order(array('relevance DESC'));
        }
        
        return $productList;
    }
    
}

?>
