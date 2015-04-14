<?php

class Gemgento_Push_Helper_Email_Template extends Mage_Core_Helper_Abstract
{

    public function sendSalesEmail($path, $data) {
        $this->send("email/sales/{$path}", $data);
    }

    public function send($path, $data) {
        Mage::getModel('gemgento_push/observer')->push('POST', $path, '', $data);
    }

    public function recipientStrings($emails, $names = []) {
        $recipients = array();

        foreach($emails as $key=>$email) {

            if (!empty($names[$key])){
                $recipients = "\"{$names[$key]}\" <{$email}>";
            } else {
                $recipients[] = $email;
            }
        }

        return $recipients;
    }

}
