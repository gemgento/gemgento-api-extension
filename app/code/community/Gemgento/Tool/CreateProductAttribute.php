<?php

require_once '../../../../Mage.php';

Mage::app();

$installer = new Mage_Customer_Model_Entity_Setup('core_setup');

$installer->startSetup();

// Add gemgento_id to products
$attrCode = 'gemgento_id';

$objCatalogEavSetup = Mage::getResourceModel('catalog/eav_mysql4_setup', 'core_setup');
$attrIdTest = $objCatalogEavSetup->getAttributeId(Mage_Catalog_Model_Product::ENTITY, $attrCode);

if ($attrIdTest === false) {
    $objCatalogEavSetup->addAttribute(Mage_Catalog_Model_Product::ENTITY, $attrCode, array(
        'group' => 'General',
        'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
        'backend' => '',
        'frontend' => '',
        'label' => 'Gemgento Id',
        'note' => 'The product id in Gemgento',
        'input' => 'text',
        'class' => '',
        'source' => '',
        'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
        'visible' => true,
        'required' => false,
        'user_defined' => true,
        'default' => '0',
        'visible_on_front' => false,
        'unique' => false,
        'is_configurable' => false,
        'used_for_promo_rules' => false
    ));
}

$installer->endSetup();
