<?php

$this->startSetup();

function createAttributeSet($setName, $inheritedAttributeSetId, $entityTypeId) {
  Mage::log("Creating Attribute Set {$setName}", Zend_Log::INFO);
  $attributeSet = Mage::getModel('eav/entity_attribute_set')->setEntityTypeId($entityTypeId)->setAttributeSetName($setName);
  $attributeSet->validate();
  $attributeSet->save()->initFromSkeleton($inheritedAttributeSetId)->save();
  Mage::log("End Creating Attribute Set {$setName}", Zend_Log::INFO);
  return $attributeSet;
}

function createGroup($groupName, $attributeSet) {
  Mage::log("Creating Group {$groupName}", Zend_Log::INFO);
  $group = Mage::getModel('eav/entity_attribute_group');
  $group->setAttributeGroupName($groupName);
  $group->setAttributeSetId($attributeSet->getId());
  $attributeSet->setGroups(array($group));
  $attributeSet->save();
  Mage::log("End Creating Group {$groupName}", Zend_Log::INFO);
  return $group;
}

function createAttribute($label, $type, $entityTypeId, $attributeSet, $group) {
  Mage::log("Creating Attribute {$label}", Zend_Log::INFO);
  $data = array(
    'is_global'                     => '0',
    'frontend_input'                => 'text',
    'default_value_text'            => '',
    'default_value_yesno'           => '0',
    'default_value_date'            => '',
    'default_value_textarea'        => '',
    'is_unique'                     => '0',
    'is_required'                   => '0',
    'frontend_class'                => '',
    'is_searchable'                 => '1',
    'is_visible_in_advanced_search' => '1',
    'is_comparable'                 => '1',
    'is_used_for_promo_rules'       => '0',
    'is_html_allowed_on_front'      => '1',
    'is_visible_on_front'           => '0',
    'used_in_product_listing'       => '0',
    'used_for_sort_by'              => '0',
    'is_configurable'               => '0',
    'is_filterable'                 => '0',
    'is_filterable_in_search'       => '0',
    'backend_type'                  => $type,
    'default_value'                 => '',
    'apply_to'                      => array('simple'),
    'attribute_code'                => str_replace(' ', '_', strtolower($label)),
    'frontend_label'                => array($label, '', '', '', '')
  );

  $attribute = Mage::getModel('catalog/resource_eav_attribute');
  $attribute->addData($data);
  $attribute->setAttributeSetId($attributeSet->getId());
  $attribute->setAttributeGroupId($group->getId());
  $attribute->setEntityTypeId($entityTypeId);
  $attribute->setIsUserDefined(1);
  $attribute->save();
  Mage::log("End Creating Attribute {$label}", Zend_Log::INFO);
  return $attribute->getId();
}

function createAttributes($attributes, $entityTypeId, $defaultAttribute, $group) {
  array_map(function($name) use($attributes, $entityTypeId, $defaultAttribute, $group) {
    return createAttribute($name, $attributes[$name], $entityTypeId, $defaultAttribute, $group);
  }, array_keys($attributes));
}

function createAttributeSetWithGroup($setName, $inheritedAttributeSetId, $entityTypeId, $attributes) {
  try {
    $attributeSet = createAttributeSet($setName, $inheritedAttributeSetId, $entityTypeId);
    if (($setId = $attributeSet->getId()) === false) {
      Mage::log('Could not get ID from new attribute set ' . $setName);
      return false;
    }

    $groupName = $setName . ' Details';
    $group = createGroup($groupName, $attributeSet);
    if (($groupId = $group->getId()) === false) {
      Mage::log('Could not get ID from new group ' . $groupName);
      return false;
    }

    createAttributes($attributes, $entityTypeId, $attributeSet, $group);
  }
  catch(Exception $ex) {
    Mage::log('Problem creating attribute set ' . $setName . ': ' . $ex->getMessage());
    return false;
  }

  return true;
}

function addPosSaleIdAttributeToOrders() {
  Mage::log("Adding POS Sale ID Attribute to orders", Zend_Log::INFO);

  $setup = new Mage_Sales_Model_Resource_Setup('core_setup');
  $setup->addAttribute('order', 'pos_sale_id', array(
    'type' => 'int',
    'label' => 'Octahedron POS sale ID',
    'visible' => false,
    'required' => false,
    'visible_on_front' => false,
    'user_defined' => false
  ));
}

$productModel = Mage::getModel('catalog/product');
$defaultAttributeSet = Mage::getModel('eav/entity_attribute_set');
$defaultAttributeSet->load($productModel->getResource()->getEntityType()->getDefaultAttributeSetId());
$entityTypeId = $productModel->getResource()->getTypeId();

$group = createGroup('Item Details', $defaultAttributeSet);
$attributes = array('Metal Type' => 'varchar', 'Comments' => 'text', 'Length' => 'decimal');
createAttributes($attributes, $entityTypeId, $defaultAttributeSet, $group);

createAttributeSetWithGroup('General', $defaultAttributeSet->getId(), $entityTypeId, array(
  'Gross Weight' => 'decimal',
  'Gold Weight' => 'decimal',
  'Diamond Weight' => 'decimal',
  'Number of Pieces' => 'decimal',
  'Gem' => 'varchar',
  'Structure' => 'varchar'
));

createAttributeSetWithGroup('Ring', $defaultAttributeSet->getId(), $entityTypeId, array(
  'Band Width' => 'decimal',
  'Band Style' => 'varchar',
  'Main Stone' => 'varchar',
  'Main Setting' => 'varchar',
  'Additional Stone' => 'varchar',
  'Additional Setting' => 'varchar',
  'Finger Size' => 'varchar',
  'Certification Number' => 'varchar',
  'Certification Agency' => 'varchar'
));

createAttributeSetWithGroup('Pearl', $defaultAttributeSet->getId(), $entityTypeId, array(
  'Pearl Type' => 'varchar',
  'Pearl Colour' => 'varchar',
  'Diameter' => 'decimal',
  'Roundness' => 'varchar',
  'Luster' => 'varchar',
  'Blemishes' => 'varchar',
  'String Type' => 'varchar',
  'Clasp' => 'varchar',
  'Number of Pearls' => 'decimal'
));

addPosSaleIdAttributeToOrders();

$this->endSetup();
