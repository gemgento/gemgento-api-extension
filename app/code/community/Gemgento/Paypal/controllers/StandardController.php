<?php

require_once(Mage::getModuleDir('controllers','Mage_Paypal').DS.'StandardController.php');

class Gemgento_Paypal_StandardController extends Mage_Paypal_StandardController {

    /**
     * When a customer chooses Paypal on Checkout/Payment page
     *
     */
    public function redirectAction()
    {

        $quote = Mage::getModel('sales/quote')->setStoreId($_GET['store_id'])->load($_GET['quote_id']);
        $session = Mage::getSingleton('checkout/session');
        $session->setPaypalStandardQuoteId($_GET['quote_id']);
        $session->setLastRealOrderId($quote->getReservedOrderId());
        $this->getResponse()->setBody($this->getLayout()->createBlock('paypal/standard_redirect')->toHtml());
        $session->unsQuoteId();
        $session->unsRedirectUrl();
    }

    /**
     * When a customer cancel payment from paypal.
     */
    public function cancelAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getPaypalStandardQuoteId(true));
        if ($session->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            if ($order->getId()) {
                $order->cancel()->save();
            }
        }
        header("Location: {$this->gemgentoUrl()}cart");
        exit;
    }

    /**
     * when paypal returns
     * The order information at this point is in POST
     * variables.  However, you don't want to "process" the order until you
     * get validation from the IPN.
     */
    public function  successAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getPaypalStandardQuoteId(true));
        Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
        header("Location: {$this->gemgentoUrl()}checkout/thank_you");
        exit;
    }

    /**
     * Get the Gemgento URL from configuration
     *
     * @return string
     */
    private function gemgentoUrl() {
        $url = Mage::getStoreConfig("gemgento_push/settings/gemgento_url");

        if (substr($url, -1) != '/') {
            $url .= '/';
        }

        return $url;
    }

}