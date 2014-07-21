<?php

class Gemgento_ApiLogger_Model_Api_Server_V2_Handler extends Mage_Api_Model_Server_V2_Handler {

    /**
     * Logs V2 API call
     *
     * @param type $sessionId
     * @param type $apiPath
     * @param type $args
     * @return mixed Null or whatever API call method returns
     */
    public function call($sessionId, $apiPath, $args = array()) {
        Mage::helper('gemgento_apilogger/v2')
                ->logPostXml();

        return parent::call($sessionId, $apiPath, $args);
    }

    /**
     * Logs V2 API fault
     *
     * @param type $faultName
     * @param type $resourceName
     * @param type $customMessage
     */
    protected function _fault($faultName, $resourceName = null, $customMessage = null) {
        Mage::helper('gemgento_apilogger/v2')
                ->logMessage('Fault while processing API call: ' . $faultName);

        parent::_fault($faultName, $resourceName, $customMessage);
    }

}
