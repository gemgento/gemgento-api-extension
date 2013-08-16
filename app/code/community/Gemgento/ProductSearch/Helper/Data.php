<?php

class Gemgento_ProductSearch_Helper_Data extends Mage_CatalogSearch_Helper_Data {

    public function GemgentoApiSetQueryText($queryText) {

        $this->_queryText = $queryText;
        if ($this->_queryText === null) {
            $this->_queryText = '';
        } else {
            if (is_array($this->_queryText)) {
                $this->_queryText = null;
            }
            $this->_queryText = trim($this->_queryText);
            $this->_queryText = Mage::helper('core/string')->cleanString($this->_queryText);

            if (Mage::helper('core/string')->strlen($this->_queryText) > $this->getMaxQueryLength()) {
                $this->_queryText = Mage::helper('core/string')->substr(
                        $this->_queryText, 0, $this->getMaxQueryLength()
                );
                $this->_isMaxLength = true;
            }
        }
        return $this;
    }

    public function getQueryText() {
        if (!$this->_queryText) {
            $this->_queryText = $this->_getRequest()->getParam($this->getQueryParamName());
            if ($this->_queryText === null) {
                $this->_queryText = '';
            } else {
                if (is_array($this->_queryText)) {
                    $this->_queryText = null;
                }
                $this->_queryText = trim($this->_queryText);
                $this->_queryText = Mage::helper('core/string')->cleanString($this->_queryText);

                if (Mage::helper('core/string')->strlen($this->_queryText) > $this->getMaxQueryLength()) {
                    $this->_queryText = Mage::helper('core/string')->substr(
                            $this->_queryText, 0, $this->getMaxQueryLength()
                    );
                    $this->_isMaxLength = true;
                }
            }
        }
        return $this->_queryText;
    }

    public function getRequest() {
        return $this->_getRequest();
    }

    public function setQueryText() {
        $this->getRequest()->setParam($this->getQueryParamName(), $this->getQueryText());
        return $this;
    }

    public function gemgentoCustomSearchInit() {
        $query = Mage::helper('catalogsearch')->getQuery();

        $query->setStoreId(Mage::app()->getStore()->getId());

        if ($query->getQueryText()) {
            if (Mage::helper('catalogsearch')->isMinQueryLength()) {
                $query->setId(0)
                        ->setIsActive(1)
                        ->setIsProcessed(1);
            } else {
                if ($query->getId()) {
                    $query->setPopularity($query->getPopularity() + 1);
                } else {
                    $query->setPopularity(1);
                }

                if ($query->getRedirect()) {
                    $query->save();
                    $this->getResponse()->setRedirect($query->getRedirect());
                    return;
                } else {
                    $query->prepare();
                }
            }
            if (!Mage::helper('catalogsearch')->isMinQueryLength()) {
                $query->save();
            }
        }
    }

}

?>
