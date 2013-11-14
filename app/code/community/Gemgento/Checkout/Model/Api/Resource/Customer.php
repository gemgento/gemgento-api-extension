<?php

class Gemgento_Checkout_Model_Api_Resource_Customer extends Mage_Checkout_Model_Api_Resource_Customer
{
    protected function _prepareGuestQuote(Mage_Sales_Model_Quote $quote)
    {
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getCustomerEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
        return $this;
    }
}
