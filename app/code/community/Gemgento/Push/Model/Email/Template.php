<?php

class Gemgento_Push_Model_Email_Template extends Mage_Core_Model_Email_Template
{

    /**
     * Send transactional email to recipient
     *
     * @param   int $templateId
     * @param   string|array $sender sneder informatio, can be declared as part of config path
     * @param   string $email recipient email
     * @param   string $name recipient name
     * @param   array $vars varianles which can be used in template
     * @param   int|null $storeId
     * @return  Mage_Core_Model_Email_Template
     */
    public function sendTransactional($templateId, $sender, $email, $name, $vars=array(), $storeId=null)
    {
        $this->setSentSuccess(false);
        if (($storeId === null) && $this->getDesignConfig()->getStore()) {
            $storeId = $this->getDesignConfig()->getStore();
        }

        if (is_numeric($templateId)) {
            $this->load($templateId);
        } else {
            $localeCode = Mage::getStoreConfig('general/locale/code', $storeId);
            $this->loadDefault($templateId, $localeCode);
        }

        if (!$this->getId()) {
            throw Mage::exception('Mage_Core', Mage::helper('core')->__('Invalid transactional email code: ' . $templateId));
        }

        if (!is_array($sender)) {
            $this->setSenderName(Mage::getStoreConfig('trans_email/ident_' . $sender . '/name', $storeId));
            $this->setSenderEmail(Mage::getStoreConfig('trans_email/ident_' . $sender . '/email', $storeId));
        } else {
            $this->setSenderName($sender['name']);
            $this->setSenderEmail($sender['email']);
        }

        if (!isset($vars['store'])) {
            $vars['store'] = Mage::app()->getStore($storeId);
        }

        if (Mage::getStoreConfig("gemgento_push/emails/enabled")) {
            $this->setSentSuccess($this->gemgentoSend($email, $name, $vars));
        } else {
            $this->setSentSuccess($this->send($email, $name, $vars));
        }

        return $this;
    }

    private function gemgentoSend($emails, $names, $vars){
        $headers = $this->getMail()->getHeaders();
        $code = is_numeric($this->getTemplateId()) ? $this->getOrigTemplateCode()  : $this->getTemplateId();

        # aggregate header information
        $data = array(
            'recipients' => array(
                  'to' => Mage::helper('gemgento_push/email_template')->recipientStrings($emails, $names),
                  'cc' => array(),
                  'bcc' => array()
            ),
            'sender' => "\"{$this->getSenderName()}\" <{$this->getSenderEmail()}>"
        );

        if (array_key_exists('Cc', $headers)) {
            unset($headers['Cc']['append']);
            $data['recipients']['cc'] = $headers['Cc'];
        }

        if (array_key_exists('Bcc', $headers)) {
            unset($headers['Bcc']['append']);
            $data['recipients']['bcc'] = $headers['Bcc'];
        }

        # include necessary objects and send
        switch($code) {

            case 'sales_email_order_template':
                $data['order'] = $vars['order']->toArray();
                Mage::helper('gemgento_push/email_template')->sendSalesEmail('orders', $data);
                break;

            case 'sales_email_invoice_template':
                $data['order'] = $vars['order']->toArray();
                $data['invoice'] = $vars['invoice']->toArray();
                Mage::helper('gemgento_push/email_template')->sendSalesEmail('invoices', $data);
                break;

            case 'sales_email_shipment_template':
                $data['order'] = $vars['order']->toArray();
                $data['shipment'] = $vars['shipment']->toArray();
                Mage::helper('gemgento_push/email_template')->sendSalesEmail('shipments', $data);
                break;

            case 'sales_email_creditmemo_template':
                $data['order'] = $vars['order']->toArray();
                $data['credit_memo'] = $vars['creditmemo']->toArray();
                Mage::helper('gemgento_push/email_template')->sendSalesEmail('credit_memos', $data);
                break;

            default:
                $data['code'] = $code;
                $data['vars'] = $vars;
                Mage::helper('gemgento_push/email_template')->send('emails', $data);
                break;
        }

        return true;
    }

}