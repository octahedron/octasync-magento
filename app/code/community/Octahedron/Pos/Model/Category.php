<?php

/**
 * Pos Category
 *
 * @category    Octahedron
 * @package     Octahedron_Pos
 * @author      andrew@octahedron.com.au
 */
class Octahedron_Pos_Model_Category {

  protected $meta;
  protected $resource;
  protected $dbRead;
  protected $dbWrite;
  protected $entityMeta;
  protected $currentCategories;

  public function __construct() {
    $this->meta = Mage::helper('octahedron_pos/meta');
    $this->resource = Mage::getSingleton('core/resource');
    $this->dbRead = $this->resource->getConnection('core_read');
    $this->dbWrite = $this->resource->getConnection('core_write');
    $this->entityMeta = $this->meta->getEntityMeta('catalog_category');
    $this->attributesMap = $this->meta->getAttributesMap($this->entityMeta['entity_type_id']);
    $this->categoriesTable = $this->resource->getTableName($this->entityMeta['entity_table'] . '/entity');
  }

  public function currentCategories() {
    if (!$this->currentCategories) {
      $query = 'SELECT value, entity_id FROM ' . $this->categoriesTable . "_varchar WHERE store_id = 0 AND value != 'Root Catalog' AND attribute_id = " . $this->attributesMap['name'];
      $this->currentCategories = array_reduce($this->dbRead->fetchAll($query), function($categories, $row) {
        $categories[$row['value']] = $row['entity_id'];
        return $categories;
      }, []);
    }
    return $this->currentCategories;
  }

  public function updateCategory($oldCategory, $newCategoryName) {
    $oldCategory->setName($newCategoryName)->save();
  }

  public function addCategory($categoryName) {
    $entityId = (int)$this->dbRead->fetchOne("SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name = '" . $this->categoriesTable . "' AND table_schema = DATABASE()");
    $query = <<<END
        INSERT INTO {$this->categoriesTable} (entity_type_id, attribute_set_id, parent_id, created_at, updated_at, path, position, level, children_count)
        VALUES (:entityTypeId, :attributeSetId, 1, now(), now(), :path, :position, 1, 0)
END;
    $this->dbWrite->query($query, [
      'entityTypeId' => $this->entityMeta['entity_type_id'],
      'attributeSetId' => $this->entityMeta['default_attribute_set_id'],
      'path' => '1/' . $entityId,
      'position' => $entityId - 1
    ]);

    $this->meta->insertEntry($this->categoriesTable, 'varchar', $this->attributesMap['name'], $this->entityMeta['entity_type_id'], $entityId, $categoryName);
    $this->meta->insertEntry($this->categoriesTable, 'varchar', $this->attributesMap['url_key'], $this->entityMeta['entity_type_id'], $entityId, $this->formatUrlKey($categoryName));
    $this->meta->insertEntry($this->categoriesTable, 'varchar', $this->attributesMap['display_mode'], $this->entityMeta['entity_type_id'], $entityId, 'PRODUCTS');
    $this->meta->insertEntry($this->categoriesTable, 'int', $this->attributesMap['is_active'], $this->entityMeta['entity_type_id'], $entityId, 1);
    $this->meta->insertEntry($this->categoriesTable, 'int', $this->attributesMap['include_in_menu'], $this->entityMeta['entity_type_id'], $entityId, 1);

    $this->currentCategories[$categoryName] = $entityId;
  }

  protected function formatUrlKey($str) {
    $str = Mage::helper('catalog/product_url')->format($str);
    $urlKey = preg_replace('#[^0-9a-z]+#i', '-', $str);
    $urlKey = strtolower($urlKey);
    $urlKey = trim($urlKey, '-');
    return $urlKey;
  }

  protected function deleteCategoryEntry($entityId, $type = null) {
    $table = $this->categoriesTable . ($type ? '_' . $type : '');
    $this->dbWrite->query('DELETE FROM ' . $table . ' WHERE entity_id = :entityId', ['entityId' => $entityId]);
  }

  public function updateCategoryCount() {
    $query = <<<END
        UPDATE  {$this->categoriesTable}
        SET     children_count = (SELECT COUNT(*) FROM {$this->categoriesTable}_varchar WHERE store_id = 0 AND value != 'Root Catalog' AND attribute_id = {$this->attributesMap['name']})
        WHERE   entity_id = 1
END;
    $this->dbWrite->query($query);
  }

  public function updateRootCategory() {
    $query = <<<END
        UPDATE  {$this->resource->getTableName('core/store')}_group
        SET     root_category_id = (SELECT entity_id FROM {$this->categoriesTable}_varchar WHERE store_id = 0 AND value != 'Root Catalog' AND attribute_id = {$this->attributesMap['name']} ORDER BY entity_id LIMIT 1)
        WHERE   group_id = 1
END;
    $this->dbWrite->query($query);
  }

  public function mergeCategory($category, $mergedFromCategoryName) {
    $productCategoryTable = $this->resource->getTableName('catalog_category_product');
    $this->dbWrite->query("UPDATE {$productCategoryTable} SET category_id = :categoryId WHERE category_id = :mergedFrom", [
      'categoryId' => $category->getId(),
      'mergedFrom' => $this->currentCategories[$mergedFromCategoryName]
    ]);
    $this->deleteCategory($mergedFromCategoryName);
  }

  public function deleteCategory($categoryName) {
    $this->deleteCategoryEntry($this->currentCategories[$categoryName], 'decimal');
    $this->deleteCategoryEntry($this->currentCategories[$categoryName], 'int');
    $this->deleteCategoryEntry($this->currentCategories[$categoryName], 'text');
    $this->deleteCategoryEntry($this->currentCategories[$categoryName], 'varchar');
    $this->deleteCategoryEntry($this->currentCategories[$categoryName], 'datetime');
    $this->deleteCategoryEntry($this->currentCategories[$categoryName]);
  }

}
