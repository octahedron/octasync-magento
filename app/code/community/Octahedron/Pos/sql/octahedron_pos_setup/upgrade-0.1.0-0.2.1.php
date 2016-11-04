<?php

$this->startSetup();

Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
$profile = Mage::getModel('dataflow/profile');
$actionsXml = <<<END
    <action type="dataflow/convert_parser_csv" method="parse">
      <var name="delimiter"><![CDATA[,]]></var>
      <var name="enclose"><![CDATA["]]></var>
      <var name="fieldnames"></var>
      <var name="map">
          <map name="sku"><![CDATA[sku]]></map>
          <map name="image"><![CDATA[image]]></map>
          <map name="small_image"><![CDATA[small_image]]></map>
          <map name="gallery"><![CDATA[gallery]]></map>
      </var>
      <var name="store"><![CDATA[0]]></var>
      <var name="number_of_records">1</var>
      <var name="decimal_separator"><![CDATA[.]]></var>
      <var name="adapter">catalog/convert_adapter_product</var>
      <var name="method">parse</var>
    </action>
END;
$profile->addData(array(
  'name' => 'Bulk Image Import',
  'entity_type' => 'product',
  'direction' => 'import',
  'store_id' => Mage_Core_Model_App::ADMIN_STORE_ID,
  'data_transfer' => 'interactive',
  'actions_xml_view' => $actionsXml,
  'gui_data' => array(
    'import' => array('number_of_records' => 1, 'decimal_separator' => '.'),
    'parse' => array('type' => 'csv', 'delimiter' => ',', 'enclose' => '"', 'fieldnames' => null),
    'map' => array(
      'only_specified' => null,
      'product' => array(
        'db' => array('sku', 'image', 'small_image'),
        'file' => array('sku', 'image', 'small_image')
      )
    )
  )
));
$profile->save();

$this->endSetup();
