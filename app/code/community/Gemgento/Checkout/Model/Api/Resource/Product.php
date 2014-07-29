<?php

class Gemgento_Checkout_Model_Api_Resource_Product extends Mage_Checkout_Model_Api_Resource_Product {

    /**
     * Get QuoteItem by Product and request info
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param Mage_Catalog_Model_Product $product
     * @param Varien_Object $requestInfo
     * @return Mage_Sales_Model_Quote_Item
     * @throw Mage_Core_Exception
     */
    protected function _getQuoteItemByProduct(Mage_Sales_Model_Quote $quote, Mage_Catalog_Model_Product $product, Varien_Object $requestInfo) {
        $cartCandidates = $product->getTypeInstance(true)
                ->prepareForCartAdvanced($requestInfo, $product, Mage_Catalog_Model_Product_Type_Abstract::PROCESS_MODE_FULL
        );

        /**
         * Error message
         */
        if (is_string($cartCandidates)) {
            throw Mage::throwException($cartCandidates);
        }

        /**
         * If prepare process return one object
         */
        if (!is_array($cartCandidates)) {
            $cartCandidates = array($cartCandidates);
        }

        /** @var $item Mage_Sales_Model_Quote_Item */
        $item = null;
        foreach ($cartCandidates as $candidate) {
            if ($candidate->getParentProductId()) {
                continue;
            }

            $item = $this->_getItemByProduct($quote, $candidate);
        }

        if (is_null($item)) {
            $item = Mage::getModel("sales/quote_item");
        }

        return $item;
    }

    /**
     * Get QuoteItem that matches Product.
     * 
     * @param Mage_Sales_Model_Quote $quote
     * @param Mage_Catalog_Model_Product $product
     * @return Mage_Sales_Model_Quote_Item | NULL
     */
    protected function _getItemByProduct(Mage_Sales_Model_Quote $quote, Mage_Catalog_Model_Product $product) {
        foreach ($quote->getAllItems() as $item) {
            if ($item->getProduct()->getId() == $product->getId()) {
                return $item;
            }
        }

        return Mage::getModel("sales/quote_item")
                ->setProduct($product)
                ->setQuote($quote);
    }

}
