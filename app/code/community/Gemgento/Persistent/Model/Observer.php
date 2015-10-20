<?php

class Gemgento_Persistent_Model_Observer extends Mage_Persistent_Model_Observer
{
    /**
     * Prevent express checkout with PayPal Express checkout
     *
     * @param Varien_Event_Observer $observer
     */
    public function preventExpressCheckout($observer)
    {
        return;
    }
}