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
  protected $rootCategoryId;

  public function __construct() {
    $this->meta = Mage::helper('octahedron_pos/meta');
    $this->resource = Mage::getSingleton('core/resource');
    $this->dbRead = $this->resource->getConnection('core_read');
    $this->dbWrite = $this->resource->getConnection('core_write');
    $this->entityMeta = $this->meta->getEntityMeta('catalog_category');
    $this->attributesMap = $this->meta->getAttributesMap($this->entityMeta['entity_type_id']);
    $this->categoriesTable = $this->resource->getTableName($this->entityMeta['entity_table'] . '/entity');
    $this->rootCategoryId = Mage::getStoreConfig('octahedron_pos/category/root');
  }

  public function currentCategories() {
    if (!$this->currentCategories) {
      $query = <<<END
          SELECT  value, entity_id
          FROM    {$this->categoriesTable}_varchar INNER JOIN {$this->categoriesTable} USING (entity_id)
          WHERE   store_id = 0
                  AND parent_id = {$this->rootCategoryId}
                  AND attribute_id = {$this->attributesMap['name']}
END;
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

  public function addCategory($categoryName, $atRootLevel = false) {
    $entityId = (int)$this->dbRead->fetchOne("SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name = '" . $this->categoriesTable . "' AND table_schema = DATABASE()");
    $query = <<<END
        INSERT INTO {$this->categoriesTable} (entity_type_id, attribute_set_id, parent_id, created_at, updated_at, path, position, level, children_count)
        VALUES (:entityTypeId, :attributeSetId, :parentId, now(), now(), :path, :position, 2, 0)
END;
    $path = $atRootLevel ? '1/' . $entityId : "1/{$this->rootCategoryId}/{$entityId}";
    $parentId = $atRootLevel ? 1 : $this->rootCategoryId;
    $this->dbWrite->query($query, [
      'entityTypeId' => $this->entityMeta['entity_type_id'],
      'attributeSetId' => $this->entityMeta['default_attribute_set_id'],
      'path' => $path,
      'parentId' => $parentId,
      'position' => $entityId - 1
    ]);

    $this->meta->insertEntry($this->categoriesTable, 'varchar', $this->attributesMap['name'], $this->entityMeta['entity_type_id'], $entityId, $categoryName);
    $this->meta->insertEntry($this->categoriesTable, 'varchar', $this->attributesMap['url_key'], $this->entityMeta['entity_type_id'], $entityId, $this->formatUrlKey($categoryName));
    $this->meta->insertEntry($this->categoriesTable, 'varchar', $this->attributesMap['display_mode'], $this->entityMeta['entity_type_id'], $entityId, 'PRODUCTS');
    $this->meta->insertEntry($this->categoriesTable, 'int', $this->attributesMap['is_active'], $this->entityMeta['entity_type_id'], $entityId, 1);
    $this->meta->insertEntry($this->categoriesTable, 'int', $this->attributesMap['include_in_menu'], $this->entityMeta['entity_type_id'], $entityId, 1);

    $this->currentCategories[$categoryName] = $entityId;
    return $entityId;
  }

  protected function formatUrlKey($str) {
    $str = Mage::helper('catalog/product_url')->format($str);
    $urlKey = preg_replace('#[^0-9a-z]+#i', '-', $str);
    $urlKey = strtolower($urlKey);
    $urlKey = trim($urlKey, '-');
    return $urlKey;
  }

  public function updateCategoryCount($atRootLevel = false) {
    $entityId = $atRootLevel ? 1 : $this->rootCategoryId;
    $query = <<<END
        UPDATE  {$this->categoriesTable} JOIN (SELECT COUNT(*) AS total FROM {$this->categoriesTable} AS c2 WHERE c2.parent_id = {$entityId}) AS count
        SET     children_count = count.total
        WHERE   entity_id = {$entityId}
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

  protected function deleteCategoryEntry($entityId, $type = null) {
    $table = $this->categoriesTable . ($type ? '_' . $type : '');
    $this->dbWrite->query('DELETE FROM ' . $table . ' WHERE entity_id = :entityId', ['entityId' => $entityId]);
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
