<?php

$installer = $this;
$installer->startSetup();

$entitiesToAlter = array('quote', 'order');
$attribute = array(
    'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
    'default' => NULL
);

foreach ($entitiesToAlter as $entityName) {
    $installer->addAttribute($entityName, 'gemgento_id', $attribute);
}

$vCustomerEntityType = $installer->getEntityTypeId('customer');
$vCustAttributeSetId = $installer->getDefaultAttributeSetId($vCustomerEntityType);
$vCustAttributeGroupId = $installer->getDefaultAttributeGroupId($vCustomerEntityType, $vCustAttributeSetId);

$installer->addAttribute('customer', 'gemgento_id', array(
        'label' => 'Gemgento Id',
        'input' => 'text',
        'type'  => 'int',
        'forms' => array('customer_account_edit','customer_account_create','adminhtml_customer','checkout_register'),
        'required' => 0,
        'user_defined' => 1,
));

$installer->addAttributeToGroup($vCustomerEntityType, $vCustAttributeSetId, $vCustAttributeGroupId, 'gemgento_id', 0);

$oAttribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'gemgento_id');
$oAttribute->setData('used_in_forms', array('customer_account_edit','customer_account_create','adminhtml_customer','checkout_register'));
$oAttribute->save();

$installer->endSetup();
