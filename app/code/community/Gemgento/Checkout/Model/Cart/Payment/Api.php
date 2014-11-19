<?php

class Gemgento_Checkout_Model_Cart_Payment_Api extends Mage_Checkout_Model_Cart_Payment_Api
{

    protected function _preparePaymentData($data) {
        if (!(is_array($data) && is_null($data[0]))) {
            return array();
        }

        if (is_array($data['additional_information'])) {
            $additional_information = array();
            foreach ($data['additional_information'] as $information) {
                if (!is_null($information->key) && !is_null($information->value)) {
                    $additional_information[$information->key] = $information->value;
                }
            }
            $data['additional_information'] = $additional_information;
        }

        return $data;
    }

    protected function _canUsePaymentMethod($method, $quote)
    {
        if ( !($method->isGateway() || $method->canUseInternal()) && strpos($method->getCode(), 'paypal') === FALSE ) {
            return false;
        }

        if (!$method->canUseForCountry($quote->getBillingAddress()->getCountry())) {
            return false;
        }

        if (!$method->canUseForCurrency(Mage::app()->getStore($quote->getStoreId())->getBaseCurrencyCode())) {
            return false;
        }

        /**
         * Checking for min/max order total for assigned payment method
         */
        $total = $quote->getBaseGrandTotal();
        $minTotal = $method->getConfigData('min_order_total');
        $maxTotal = $method->getConfigData('max_order_total');

        if ((!empty($minTotal) && ($total < $minTotal)) || (!empty($maxTotal) && ($total > $maxTotal))) {
            return false;
        }

        return true;
    }
}
