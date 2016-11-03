<?php

/**
 * Pos Stock
 *
 * @category    Octahedron
 * @package     Octahedron_Pos
 * @author      andrew@octahedron.com.au
 */
class Octahedron_Pos_Model_Stock {

  protected $meta;
  protected $resource;
  protected $dbRead;
  protected $dbWrite;
  protected $entityMeta;
  protected $productsTable;
  protected $currentCategories;
  protected $currentTaxClasses;

  public function __construct() {
    $this->meta = Mage::helper('octahedron_pos/meta');
    $this->resource = Mage::getSingleton('core/resource');
    $this->dbRead = $this->resource->getConnection('core_read');
    $this->dbWrite = $this->resource->getConnection('core_write');
    $this->entityMeta = $this->meta->getEntityMeta('catalog_product');
    $this->productsTable = $this->resource->getTableName($this->entityMeta['entity_table'] . '/entity');
    $this->currentCategories = Mage::getSingleton('octahedron_pos/category')->currentCategories();
    $this->currentTaxClasses = Mage::getSingleton('octahedron_pos/tax')->currentTaxClasses();
  }

  public function getCurrentStock() {
    return array_reduce($this->dbRead->fetchAll('SELECT sku, updated_at FROM ' . $this->productsTable), function($products, $product) {
      $products[$product['sku']] = $product['updated_at'];
      return $products;
    }, []);
  }

  public function updateStockItem($localProduct, $remoteStockDetails) {
    Mage::log('Updating Product ' . $localProduct->getSku(), Zend_Log::INFO);
    $localProduct->setPrice($remoteStockDetails['sale_price_tax_exc_raw']);
    $localProduct->setIsMassupdate(true);
    $localProduct->setExcludeUrlRewrite(true);

    $localProduct->setShortDescription($remoteStockDetails['stock_number']);
    $localProduct->setDescription($remoteStockDetails['item_description']);
    $localProduct->setWeight($remoteStockDetails['item_weight'] ? $remoteStockDetails['item_weight'] : 0);
    $localProduct->setLength($remoteStockDetails['item_length']);
    $localProduct->setMetalType($remoteStockDetails['metal_type']);
    $localProduct->setComments($remoteStockDetails['comments']);
    $localProduct->setPrice($remoteStockDetails['sale_price_tax_exc_raw']);

    if ($remoteStockDetails['form_type'] === 'General') {
      $localProduct->setGrossWeight($remoteStockDetails['general_gross_weight']);
      $localProduct->setGoldWeight($remoteStockDetails['general_gold_weight']);
      $localProduct->setDiamondWeight($remoteStockDetails['general_diamond_weight']);
      $localProduct->setNumberOfPieces($remoteStockDetails['general_no_of_pieces']);
      $localProduct->setGem($remoteStockDetails['general_gem']);
      $localProduct->setStructure($remoteStockDetails['general_structure']);
    }
    else if ($remoteStockDetails['form_type'] === 'Ring') {
      $localProduct->setBandWidth($remoteStockDetails['ring_band_width']);
      $localProduct->setBandStyle($remoteStockDetails['ring_band_style']);
      $localProduct->setMainStone($remoteStockDetails['ring_main_stone']);
      $localProduct->setMainStoneSetting($remoteStockDetails['ring_main_stone_setting']);
      $localProduct->setAdditionalStone($remoteStockDetails['ring_additional_stone']);
      $localProduct->setAdditionalStoneSetting($remoteStockDetails['ring_additional_stone_setting']);
      $localProduct->setFingerSize($remoteStockDetails['ring_finger_size']);
      $localProduct->setCertificationNumber($remoteStockDetails['ring_cert_no']);
      $localProduct->setCertificationAgency($remoteStockDetails['ring_cert_agency']);
    }
    else if ($remoteStockDetails['form_type'] === 'Pearl') {
      $localProduct->setPearlType($remoteStockDetails['pearl_type']);
      $localProduct->setPearlColour($remoteStockDetails['pearl_colour']);
      $localProduct->setDiameter($remoteStockDetails['pearl_diameter']);
      $localProduct->setRoundness($remoteStockDetails['pearl_roundness']);
      $localProduct->setLuster($remoteStockDetails['pearl_luster']);
      $localProduct->setBlemishes($remoteStockDetails['pearl_blemishes']);
      $localProduct->setStringType($remoteStockDetails['pearl_string_type']);
      $localProduct->setClasp($remoteStockDetails['pearl_clasp']);
      $localProduct->setNumberOfPearls($remoteStockDetails['pearl_no_pearls']);
    }

    $localProduct->setUpdatedAt($remoteStockDetails['date_modified']);
    $localProduct->save();

    $localStockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($localProduct->getId());
    $localStockItem->setData('qty', $remoteStockDetails['qty'] === '-' ? 0 : (int)$remoteStockDetails['qty']);
    $localStockItem->setData('is_in_stock', (int)$remoteStockDetails['is_in_stock']);
    $localStockItem->save();
    Mage::log('End Updating Product ' . $localProduct->getSku(), Zend_Log::INFO);
  }

  public function addStockItem($item) {
    $entityTypeId = $this->entityMeta['entity_type_id'];
    $attributesMap = $this->meta->getAttributesMap($entityTypeId);
    $attributeSetsTable = $this->resource->getTableName('eav/attribute_set');
    $query = 'SELECT attribute_set_id, attribute_set_name FROM ' . $attributeSetsTable . ' WHERE entity_type_id = ' . $entityTypeId;
    $attributeSetsMap = array_reduce($this->dbRead->fetchAll($query), function($attributeSetsMap, $row) {
      $attributeSetsMap[$row['attribute_set_name']] = $row['attribute_set_id'];
      return $attributeSetsMap;
    }, []);

    $entityId = (int)$this->dbRead->fetchOne("SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name = '" . $this->productsTable . "' AND table_schema = DATABASE()");
    $query = <<<END
        INSERT INTO {$this->productsTable} (entity_type_id, attribute_set_id, type_id, sku, created_at, updated_at)
        VALUES (:entityTypeId, :attributeSetId, 'simple', :sku, :dateCreated, :dateModified)
END;
    $attributeSetId = isset($attributeSetsMap[$item['form_type']]) ?
        $attributeSetsMap[$item['form_type']] : $attributeSetsMap['Default'];

    $this->dbWrite->query($query, [
      'entityTypeId' => $entityTypeId,
      'attributeSetId' => $attributeSetId,
      'sku' => $item['stock_number'],
      'dateCreated' => $item['date_in_stock'],
      'dateModified' => $item['date_modified']
    ]);

    $productWebsiteTable = $this->resource->getTableName('catalog_product_website');
    $this->dbWrite->query("INSERT INTO {$productWebsiteTable} (product_id, website_id) VALUES (:entityId, 1)", ['entityId' => $entityId]);

    $productCategoryTable = $this->resource->getTableName('catalog_category_product');
    $this->dbWrite->query("INSERT INTO {$productCategoryTable} (category_id, product_id, position) VALUES (:categoryId, :entityId, 1)", [
      'categoryId' => $this->currentCategories[$item['category']],
      'entityId' => $entityId
    ]);

    $stockQuantityTable = $this->resource->getTableName('cataloginventory_stock_item');
    $params = array(
      'entityId' => $entityId,
      'qty' => $item['qty'] === '-' ? 0 : $item['qty'],
      'isDecimal' => 0,
      'isInStock' => (int)$item['is_in_stock']
    );
    if ($item['form_type'] === 'Quantity') $params['isDecimal'] = 1;
    $this->dbWrite->query("INSERT INTO {$stockQuantityTable} (product_id, stock_id, qty, is_qty_decimal, is_in_stock) VALUES (:entityId, 1, :qty, :isDecimal, :isInStock)", $params);

    $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['url_key'], $entityTypeId, $entityId, strtolower($item['stock_number']));
    $this->meta->insertEntry($this->productsTable, 'int', $attributesMap['status'], $entityTypeId, $entityId, 1);
    $this->meta->insertEntry($this->productsTable, 'int', $attributesMap['visibility'], $entityTypeId, $entityId, Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
    $this->meta->insertEntry($this->productsTable, 'text', $attributesMap['short_description'], $entityTypeId, $entityId, $item['stock_number']);

    $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['name'], $entityTypeId, $entityId, $item['stock_number']);
    $this->meta->insertEntry($this->productsTable, 'text', $attributesMap['description'], $entityTypeId, $entityId, $item['item_description']);
    $this->meta->insertEntry($this->productsTable, 'decimal', $attributesMap['weight'], $entityTypeId, $entityId, $item['item_weight'] ? $item['item_weight'] : 0);
    $this->meta->insertEntry($this->productsTable, 'decimal', $attributesMap['length'], $entityTypeId, $entityId, $item['item_length']);
    $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['metal_type'], $entityTypeId, $entityId, $item['metal_type']);
    $this->meta->insertEntry($this->productsTable, 'text', $attributesMap['comments'], $entityTypeId, $entityId, $item['comments']);

    $this->meta->insertEntry($this->productsTable, 'decimal', $attributesMap['price'], $entityTypeId, $entityId, $item['sale_price_tax_exc_raw']);
    $this->meta->insertEntry($this->productsTable, 'int', $attributesMap['tax_class_id'], $entityTypeId, $entityId, $this->currentTaxClasses['Taxable Goods']);

    if ($item['form_type'] === 'General') {
      $this->meta->insertEntry($this->productsTable, 'decimal', $attributesMap['gross_weight'], $entityTypeId, $entityId, $item['general_gross_weight']);
      $this->meta->insertEntry($this->productsTable, 'decimal', $attributesMap['gold_weight'], $entityTypeId, $entityId, $item['general_gold_weight']);
      $this->meta->insertEntry($this->productsTable, 'decimal', $attributesMap['diamond_weight'], $entityTypeId, $entityId, $item['general_diamond_weight']);
      $this->meta->insertEntry($this->productsTable, 'decimal', $attributesMap['number_of_pieces'], $entityTypeId, $entityId, $item['general_no_of_pieces']);
      $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['gem'], $entityTypeId, $entityId, $item['general_gem']);
      $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['structure'], $entityTypeId, $entityId, $item['general_structure']);
    }
    else if ($item['form_type'] === 'Ring') {
      $this->meta->insertEntry($this->productsTable, 'decimal', $attributesMap['band_width'], $entityTypeId, $entityId, $item['ring_band_width']);
      $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['band_style'], $entityTypeId, $entityId, $item['ring_band_style']);
      $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['main_stone'], $entityTypeId, $entityId, $item['ring_main_stone']);
      $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['main_setting'], $entityTypeId, $entityId, $item['ring_main_stone_setting']);
      $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['additional_stone'], $entityTypeId, $entityId, $item['ring_additional_stone']);
      $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['additional_setting'], $entityTypeId, $entityId, $item['ring_additional_stone_setting']);
      $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['finger_size'], $entityTypeId, $entityId, $item['ring_finger_size']);
      $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['certification_number'], $entityTypeId, $entityId, $item['ring_cert_no']);
      $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['certification_agency'], $entityTypeId, $entityId, $item['ring_cert_agency']);
    }
    else if ($item['form_type'] === 'Pearl') {
      $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['pearl_type'], $entityTypeId, $entityId, $item['pearl_type']);
      $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['pearl_colour'], $entityTypeId, $entityId, $item['pearl_colour']);
      $this->meta->insertEntry($this->productsTable, 'decimal', $attributesMap['diameter'], $entityTypeId, $entityId, $item['pearl_diameter']);
      $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['roundness'], $entityTypeId, $entityId, $item['pearl_roundness']);
      $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['luster'], $entityTypeId, $entityId, $item['pearl_luster']);
      $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['blemishes'], $entityTypeId, $entityId, $item['pearl_blemishes']);
      $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['string_type'], $entityTypeId, $entityId, $item['pearl_string_type']);
      $this->meta->insertEntry($this->productsTable, 'varchar', $attributesMap['clasp'], $entityTypeId, $entityId, $item['pearl_clasp']);
      $this->meta->insertEntry($this->productsTable, 'decimal', $attributesMap['number_of_pearls'], $entityTypeId, $entityId, $item['pearl_no_pearls']);
    }
  }

  public function deleteStockItem($stockItem) {
    $stockItem->delete();
  }

}
