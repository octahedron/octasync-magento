<?php

/**
 * Octahedron Meta
 *
 * @category    Octahedron
 * @package     Octahedron_Pos
 * @author      andrew@octahedron.com.au
 */
class Octahedron_Pos_Helper_Meta {

  protected $resource;
  protected $dbRead;
  protected $dbWrite;

  public function __construct() {
    $this->resource = Mage::getSingleton('core/resource');
    $this->dbRead = $this->resource->getConnection('core_read');
    $this->dbWrite = $this->resource->getConnection('core_write');
  }

  public function getEntityMeta($code) {
    $entityTypeTable = $this->resource->getTableName('eav/entity_type');
    return $this->dbRead->fetchRow("SELECT * FROM {$entityTypeTable} WHERE entity_type_code = '{$code}'");
  }

  public function getAttributesMap($entityTypeId) {
    if (!isset($this->attributeMaps[$entityTypeId])) {
      $attributesTable = $this->resource->getTableName('eav/attribute');
      $query = 'SELECT attribute_id, attribute_code FROM ' . $attributesTable . ' WHERE entity_type_id = ' . $entityTypeId;
      $this->attributeMaps[$entityTypeId] = array_reduce($this->dbRead->fetchAll($query), function($attributes, $row) {
        $attributes[$row['attribute_code']] = $row['attribute_id'];
        return $attributes;
      }, []);
    }
    return $this->attributeMaps[$entityTypeId];
  }

  public function insertEntry($table, $type, $attributeId, $entityTypeId, $entityId, $value) {
    $query = <<<END
        INSERT INTO {$table}_{$type} (entity_type_id, attribute_id, store_id, entity_id, value)
        VALUES (:entityTypeId, :attributeId, 0, :entityId, :value)
END;
    $this->dbWrite->query($query, ['entityTypeId' => $entityTypeId, 'attributeId' => $attributeId, 'entityId' => $entityId, 'value' => $value]);
  }

}
