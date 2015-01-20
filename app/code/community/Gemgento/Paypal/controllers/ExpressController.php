<?php

require_once(Mage::getModuleDir('controllers','Mage_Paypal').DS.'ExpressController.php');

class Gemgento_Paypal_ExpressController extends Mage_Paypal_ExpressController
{

    /**
     * Start Express Checkout by requesting initial token and dispatching customer to PayPal
     */
    public function startAction()
    {
        Mage::getSingleton('core/session')->renewSession();
        Mage::getSingleton('core/session')->unsSessionHosts();

        // Create session from Gemgento data
        if(!empty($_GET['store_id'])) {
            Mage::getSingleton('checkout/session')->setStoreId($_GET['store_id']);
            Mage::app()->setCurrentStore($_GET['store_id']);
        }

        if(!empty($_GET['customer_id'])) {
            Mage::getSingleton('customer/session')->logout();
            Mage::getSingleton('customer/session')->loginById($_GET['customer_id']);
        } else {
            Mage::getSingleton('customer/session')->logout();
        }

        if(!empty($_GET['quote_id'])) {
            Mage::getSingleton('checkout/session')->setQuoteId($_GET['quote_id']);
        }

        try {
            $this->_initCheckout();

            if ($this->_getQuote()->getIsMultiShipping()) {
                $this->_getQuote()->setIsMultiShipping(false);
                $this->_getQuote()->removeAllAddresses();
            }

            $customer = Mage::getSingleton('customer/session')->getCustomer();
            if ($customer && $customer->getId()) {
                $this->_checkout->setCustomerWithAddressChange(
                    $customer, $this->_getQuote()->getBillingAddress(), $this->_getQuote()->getShippingAddress()
                );
            }

            // billing agreement
            $isBARequested = (bool)$this->getRequest()
                ->getParam(Mage_Paypal_Model_Express_Checkout::PAYMENT_INFO_TRANSPORT_BILLING_AGREEMENT);
            if ($customer && $customer->getId()) {
                $this->_checkout->setIsBillingAgreementRequested($isBARequested);
            }

            // giropay
            $this->_checkout->prepareGiropayUrls(
                Mage::getUrl('checkout/onepage/success'),
                Mage::getUrl('paypal/express/cancel'),
                Mage::getUrl('checkout/onepage/success')
            );

            $token = $this->_checkout->start(Mage::getUrl('*/*/return'), Mage::getUrl('*/*/cancel'));
            if ($token && $url = $this->_checkout->getRedirectUrl()) {
                $this->_initToken($token);
                $this->getResponse()->setRedirect($url);
                return;
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckoutSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getCheckoutSession()->addError($this->__('Unable to start Express Checkout.'));
            Mage::logException($e);
        }

        header("Location: {$this->_gemgentoUrl()}checkout/address");
        exit;
    }

    /**
     * Cancel Express Checkout
     */
    public function cancelAction()
    {
        if ($storeId = Mage::getSingleton('checkout/session')->getStoreId()) {
            Mage::app()->setCurrentStore( $storeId );
        }

        try {
            $this->_initToken(false);
            // if there is an order - cancel it
            $orderId = $this->_getCheckoutSession()->getLastOrderId();
            $order = ($orderId) ? Mage::getModel('sales/order')->load($orderId) : false;
            if ($order && $order->getId() && $order->getQuoteId() == $this->_getCheckoutSession()->getQuoteId()) {
                $order->cancel()->save();
                $this->_getCheckoutSession()
                    ->unsLastQuoteId()
                    ->unsLastSuccessQuoteId()
                    ->unsLastOrderId()
                    ->unsLastRealOrderId()
                    ->addSuccess($this->__('Express Checkout and Order have been canceled.'))
                ;
            } else {
                $this->_getCheckoutSession()->addSuccess($this->__('Express Checkout has been canceled.'));
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckoutSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getCheckoutSession()->addError($this->__('Unable to cancel Express Checkout.'));
            Mage::logException($e);
        }

        header("Location: {$this->_gemgentoUrl()}cart");
        exit;
    }

    /**
     * Return from PayPal and dispatch customer to order review page
     */
    public function returnAction()
    {
        if ($storeId = Mage::getSingleton('checkout/session')->getStoreId()) {
            Mage::app()->setCurrentStore( $storeId );
        }

        try {
            $this->_initCheckout();
            $this->_checkout->returnFromPaypal($this->_initToken());
            header("Location: {$this->_gemgentoUrl()}checkout/confirm");
            exit;
        }
        catch (Mage_Core_Exception $e) {
            Mage::getSingleton('checkout/session')->addError($e->getMessage());
        }
        catch (Exception $e) {
            Mage::getSingleton('checkout/session')->addError($this->__('Unable to process Express Checkout approval.'));
            Mage::logException($e);
        }

        header("Location: {$this->_gemgentoUrl()}cart?alert=" . urlencode('There was a problem processing the PayPal payment.  Your order has been canceled.'));
        exit;
    }

    /**
     * Submit the order
     */
    public function placeOrderAction()
    {
        if ($storeId = Mage::getSingleton('checkout/session')->getStoreId()) {
            Mage::app()->setCurrentStore( $storeId );
        }

        try {
            $requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds();
            if ($requiredAgreements) {
                $postedAgreements = array_keys($this->getRequest()->getPost('agreement', array()));
                if (array_diff($requiredAgreements, $postedAgreements)) {
                    Mage::throwException(Mage::helper('paypal')->__('Please agree to all the terms and conditions before placing the order.'));
                }
            }

            $this->_initCheckout();
            $this->_checkout->place($this->_initToken());

            // prepare session to success or cancellation page
            $session = $this->_getCheckoutSession();
            $session->clearHelperData();

            // "last successful quote"
            $quoteId = $this->_getQuote()->getId();
            $session->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);

            // an order may be created
            $order = $this->_checkout->getOrder();
            if ($order) {
                $session->setLastOrderId($order->getId())
                    ->setLastRealOrderId($order->getIncrementId());
                // as well a billing agreement can be created
                $agreement = $this->_checkout->getBillingAgreement();
                if ($agreement) {
                    $session->setLastBillingAgreementId($agreement->getId());
                }
            }

            // recurring profiles may be created along with the order or without it
            $profiles = $this->_checkout->getRecurringPaymentProfiles();
            if ($profiles) {
                $ids = array();
                foreach($profiles as $profile) {
                    $ids[] = $profile->getId();
                }
                $session->setLastRecurringProfileIds($ids);
            }

            // redirect if PayPal specified some URL (for example, to Giropay bank)
            $url = $this->_checkout->getRedirectUrl();
            if ($url) {
                $this->getResponse()->setRedirect($url);
                return;
            }
            $this->_initToken(false); // no need in token anymore

            Mage::getSingleton('customer/session')->logout();
            Mage::getSingleton('core/session')->renewSession();
            Mage::getSingleton('core/session')->unsSessionHosts();

            header("Location: {$this->_gemgentoUrl()}checkout/thank_you");
            exit;
        }
        catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        catch (Exception $e) {
            $this->_getSession()->addError($this->__('Unable to place the order.'));
            Mage::logException($e);
        }

        header("Location: {$this->_gemgentoUrl()}checkout/confirm?alert=" . urlencode('There was a problem processing the PayPal payment'));
        exit;
    }

    /**
     * Instantiate quote and checkout
     * @throws Mage_Core_Exception
     */
    private function _initCheckout()
    {
        $quote = $this->_getQuote();

        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->getResponse()->setHeader('HTTP/1.1','403 Forbidden');
            Mage::throwException(Mage::helper('paypal')->__('Unable to initialize Express Checkout.'));
        }

        $this->_checkout = Mage::getSingleton($this->_checkoutType, array(
            'config' => $this->_config,
            'quote'  => $quote,
        ));
    }

    /**
     * PayPal session instance getter
     *
     * @return Mage_PayPal_Model_Session
     */
    private function _getSession()
    {
        return Mage::getSingleton('paypal/session');
    }

    /**
     * Return checkout session object
     *
     * @return Mage_Checkout_Model_Session
     */
    private function _getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Return checkout quote object
     *
     * @return Mage_Sale_Model_Quote
     */
    private function _getQuote()
    {
        if (!$this->_quote) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();

        }
        return $this->_quote;
    }

    /**
     * Get the Gemgento URL from configuration
     *
     * @return string
     */
    private function _gemgentoUrl() {
        $url = Mage::getStoreConfig("gemgento_push/settings/gemgento_url");

        if (substr($url, -1) != '/') {
            $url .= '/';
        }

        return $url;
    }

}
