<?php

class Gemgento_Push_Helper_Sales_Order extends Mage_Core_Helper_Abstract
{

    public function export($order) {
        $data = Mage::getModel('gemgento_push/observer')->_getAttributes($order, 'order');
        $data['order_id'] = $order->getId();
        $data['gemgento_id'] = $order->getGemgentoId();
        $data['store_id'] = $order->getStoreId();
        $data['shipping_address'] = Mage::getModel('gemgento_push/observer')->_getAttributes($order->getShippingAddress(), 'order_address');
        $data['billing_address'] = Mage::getModel('gemgento_push/observer')->_getAttributes($order->getBillingAddress(), 'order_address');
        $data['items'] = $this->_lineItems($order->getAllItems());
        $data['status_history'] = $this->_statusHistory($order->getAllStatusHistory());
        $data['payment'] = Mage::getModel('gemgento_push/observer')->_getAttributes($order->getPayment(), 'order_payment');

        return $data;
    }

    private function _lineItems($lineItems) {
        $data = array();

        foreach ($lineItems as $item) {
            if ($item->getGiftMessageId() > 0) {
                $item->setGiftMessage(
                    Mage::getSingleton('giftmessage/message')->load($item->getGiftMessageId())->getMessage()
                );
            }

            $data[] = Mage::getModel('gemgento_push/observer')->_getAttributes($item, 'order_item');
        }

        return $data;
    }

    private function _statusHistory($statusHistory) {
        $data = array();

        foreach ($statusHistory as $history) {
            $data[] = Mage::getModel('gemgento_push/observer')->_getAttributes($history, 'order_status_history');
        }

        return $data;
    }

}