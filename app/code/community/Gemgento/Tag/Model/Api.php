<?php

class Gemgento_Tag_Model_Api extends Mage_Tag_Model_Api {

    protected function _initTag($tagId, $storeId) {
        $model = Mage::getModel('tag/tag');
        $model->setStoreId($storeId);

        if ($tagId !== NULL) {
            $model->setAddBasePopularity();
            $model->load($tagId);
            $model->setStoreId($storeId);

            if (!$model->getId()) {
                return false;
            }
        }

        Mage::register('current_tag', $model);
        return $model;
    }

    public function manage($name, $status, $basePopularity, $productIds, $store, $tagId = NULL) {
        if ($tagId !== NULL) {
            $data['tag_id'] = $tagId;
        }

        $data['name'] = $name;
        $data['status'] = $status;
        $data['base_popularity'] = $basePopularity;
        $data['store'] = $store;

        if (!$model = $this->_initTag($tagId, $store)) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Wrong tag was specified.'));
            return $this->_redirect('*/*/index', array('store' => $data['store']));
        }

        $model->addData($data);
        $model->setData('tag_assigned_products', $productIds);

        try {
            $model->save();
        } catch (Exception $e) {
            $this->_fault('save_error', $e->getMessage());
        }

        return $model->getId();
    }

}
