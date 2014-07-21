<?php

class Gemgento_ApiLogger_Model_Api_Server_Handler extends Mage_Api_Model_Server_Handler {

    /**
     * Logs V1 API call
     *
     * @param type $sessionId
     * @param type $apiPath
     * @param type $args
     * @return mixed Null or whatever API call method returns
     */
    public function call($sessionId, $apiPath, $args = array()) {
        Mage::helper('gemgento_apilogger/v1')
                ->logPostXml();

        return parent::call($sessionId, $apiPath, $args);
    }

    /**
     * Logs V1 API fault
     *
     * @param type $faultName
     * @param type $resourceName
     * @param type $customMessage
     */
    protected function _fault($faultName, $resourceName = null, $customMessage = null) {
        Mage::helper('gemgento_apilogger/v1')
                ->logMessage('Fault while processing API call: ' . $faultName);

        parent::_fault($faultName, $resourceName, $customMessage);
    }

}
