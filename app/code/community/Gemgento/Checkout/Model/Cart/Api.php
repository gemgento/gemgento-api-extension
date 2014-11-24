<?php

class Gemgento_Checkout_Model_Cart_Api extends Mage_Checkout_Model_Cart_Api {

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

    /**
     * Create new quote for shopping cart
     *
     * @param int|string $store
     * @return int
     */
    public function create($store = null, $gemgentoId = null) {
        $storeId = $this->_getStoreId($store);

        try {
            /* @var $quote Mage_Sales_Model_Quote */
            $quote = Mage::getModel('sales/quote');
            $quote->setStoreId($storeId)
                ->setIsActive(true)
                ->setIsMultiShipping(false)
                ->setGemgentoId($gemgentoId)
                ->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('create_quote_fault', $e->getMessage());
        }
        return (int) $quote->getId();
    }

    /**
     * Create an order from the shopping cart (quote)
     *
     * @param  $quoteId
     * @param  $store
     * @param  $agreements array
     * @param  $paymentData array
     * @param  $remoteIp string
     * @param  $sendEmail boolean
     * @return string
     */
    public function createOrder($quoteId, $store = null, $agreements = null, $paymentData = null, $remoteIp = null, $sendEmail = true) {
        $requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds();

        if (!empty($requiredAgreements)) {
            $diff = array_diff($agreements, $requiredAgreements);
            if (!empty($diff)) {
                $this->_fault('required_agreements_are_not_all');
            }
        }

        $quote = $this->_getQuote($quoteId, $store);
        if ($quote->getIsMultiShipping()) {
            $this->_fault('invalid_checkout_type');
        }
        if ($quote->getCheckoutMethod() == Mage_Checkout_Model_Api_Resource_Customer::MODE_GUEST && !Mage::helper('checkout')->isAllowedGuestCheckout($quote, $quote->getStoreId())) {
            $this->_fault('guest_checkout_is_not_enabled');
        }

        // set the customers ip 
        if ($remoteIp == null) {
            $remoteIp = Mage::helper('core/http')->getRemoteAddr();
        }

        $quote->setRemoteIp($remoteIp)->save();

        /** @var $customerResource Mage_Checkout_Model_Api_Resource_Customer */
        $customerResource = Mage::getModel("checkout/api_resource_customer");
        $isNewCustomer = $customerResource->prepareCustomerForQuote($quote);

        try {
            $quote->collectTotals();
            /** @var $service Mage_Sales_Model_Service_Quote */
            $service = Mage::getModel('sales/service_quote', $quote);

            // cc_number and cc_cid are lost because API is stateless, need to add them back
            if ($paymentData != null) {
                $paymentData = $this->_preparePaymentData($paymentData);
                $service->getQuote()->getPayment()->importData($paymentData);
            }

            $service->submitAll();

            if ($isNewCustomer) {
                try {
                    $customerResource->involveNewCustomer($quote);
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }

            $order = $service->getOrder();
            if ($order) {
                Mage::dispatchEvent('checkout_type_onepage_save_order_after', array('order' => $order, 'quote' => $quote));

                if ($sendEmail) {
                    try {
                        $order->sendNewOrderEmail();
                    } catch (Exception $e) {
                        Mage::logException($e);
                    }
                }
            }

            Mage::dispatchEvent(
                'checkout_submit_all_after', array('order' => $order, 'quote' => $quote)
            );
        } catch (Mage_Core_Exception $e) {
            $this->_fault('create_order_fault', $e->getMessage());
        }

        return $order->getIncrementId();
    }

}
