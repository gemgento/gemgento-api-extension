<?php

class Gemgento_ApiLogger_Helper_Data extends Mage_Core_Helper_Abstract {

    protected $logFileName = 'soap.log';

    public function logPostXml() {
        if (($postData = file_get_contents('php://input'))) {
            // DOMDocument
            $apiDomDocument = new DOMDocument('1.0');

            // Remove white space
            $apiDomDocument->preserveWhiteSpace = false;

            // Format output for logging into file
            $apiDomDocument->formatOutput = true;

            // Load XML
            $apiDomDocument->loadXML($postData);

            if ($apiDomDocument->loadXML($postData)) {
                $this->logMessage($apiDomDocument->saveXML());
            }
        }
    }

    public function logMessage($message) {
        if ($this->_isLoggingEnabled()) {
            Mage::log(
                    $message, null, $this->logFileName
            );
        }
    }

    protected function _isLoggingEnabled() {
        return Mage::getStoreConfig("gemgento_apilogger/settings/gemgento_apilogger_enabled");
    }

}
