<?php
class Gemgento_Catalog_Model_Category_Api_V2 extends Gemgento_Catalog_Model_Category_Api 
{
    /**
     * Retrieve category data
     *
     * @param int $categoryId
     * @param string|int $store
     * @param array $attributes
     * @return array
     */
    public function info($categoryId, $store = null, $attributes = null)
    {
        $category = $this->_initCategory($categoryId, $store);

        // Basic category data
        $result = array();
        $result['category_id'] = $category->getId();

        $result['is_active']   = $category->getIsActive();
        $result['position']    = $category->getPosition();
        $result['level']       = $category->getLevel();

        foreach ($category->getAttributes() as $attribute) {
            if ($this->_isAllowedAttribute($attribute, $attributes)) {
                $result[$attribute->getAttributeCode()] = $category->getDataUsingMethod($attribute->getAttributeCode());
            }
        }
        $result['parent_id']   = $category->getParentId();
        $result['children']           = $category->getChildren();
        $result['all_children']       = $category->getAllChildren();

        return $result;
    }

    /**
     * Create new category
     *
     * @param int $parentId
     * @param array $categoryData
     * @return int
     */
    public function create($parentId, $categoryData, $store = null)
    {
        $parent_category = $this->_initCategory($parentId, $store);

        /* @var $category Mage_Catalog_Model_Category */
        $category = Mage::getModel('catalog/category')
            ->setStoreId($this->_getStoreId($store));

        $category->addData(array('path'=>implode('/',$parent_category->getPathIds())));

        $category ->setAttributeSetId($category->getDefaultAttributeSetId());


        foreach ($category->getAttributes() as $attribute) {
            $_attrCode = $attribute->getAttributeCode();
            if ($this->_isAllowedAttribute($attribute)
                && isset($categoryData->$_attrCode)) {
                $category->setData(
                    $attribute->getAttributeCode(),
                    $categoryData->$_attrCode
                );
            }
        }
        $category->setParentId($parent_category->getId());
        try {
            $validate = $category->validate();
            if ($validate !== true) {
                foreach ($validate as $code => $error) {
                    if ($error === true) {
                        Mage::throwException(Mage::helper('catalog')->__('Attribute "%s" is required.', $code));
                    }
                    else {
                        Mage::throwException($error);
                    }
                }
            }

            $category->save();
        }
        catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }

        return $category->getId();
    }

    /**
     * Update category data
     *
     * @param int $categoryId
     * @param array $categoryData
     * @param string|int $store
     * @return boolean
     */
    public function update($categoryId, $categoryData, $store = null)
    {
        $category = $this->_initCategory($categoryId, $store);

        foreach ($category->getAttributes() as $attribute) {
            $_attrCode = $attribute->getAttributeCode();
            if ($this->_isAllowedAttribute($attribute)
                && isset($categoryData->$_attrCode)) {
                $category->setData(
                    $attribute->getAttributeCode(),
                    $categoryData->$_attrCode
                );
            }
        }

        try {
            $validate = $category->validate();
            if ($validate !== true) {
                foreach ($validate as $code => $error) {
                    if ($error === true) {
                        Mage::throwException(Mage::helper('catalog')->__('Attribute "%s" is required.', $code));
                    }
                    else {
                        Mage::throwException($error);
                    }
                }
            }
            $category->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        } catch (Mage_Eav_Model_Entity_Attribute_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }

        return true;
    }
}

