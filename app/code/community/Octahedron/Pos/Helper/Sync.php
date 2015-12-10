<?php

/**
 * Pos sync
 *
 * @category    Octahedron
 * @package     Octahedron_Pos
 * @author      andrew@octahedron.com.au
 */
class Octahedron_Pos_Helper_Sync extends Mage_Core_Helper_Abstract {

  protected $api;
  protected $resource;
  protected $dbWrite;

  public function __construct() {
    $this->api = Mage::helper('octahedron_pos/api');
    $this->resource = Mage::getSingleton('core/resource');
    $this->dbWrite = $this->resource->getConnection('core_write');
  }

  public function run() {
    $this->dbWrite->beginTransaction();
    try {
      $this->disableAutoIndex();
      $lastSync = Mage::getStoreConfig('octahedron_pos/api/last_sync');
      $status = [];
      $this->syncCategories($status, $lastSync);
      $this->syncTaxValues($status, $lastSync);
      $this->syncStock($status, $lastSync);
      $this->dbWrite->commit();
    }
    catch (Exception $e) {
      $this->dbWrite->rollback();
      throw $e;
    }
    Mage::getConfig()->saveConfig('octahedron_pos/api/last_sync', time());
    $this->enableAutoIndexAndReindexData();
    Mage::app()->cleanCache();
    echo json_encode($status);
  }

  protected function disableAutoIndex() {
    $processCollection = Mage::getSingleton('index/indexer')->getProcessesCollection();
    foreach ($processCollection as $process) {
      $process->setMode(Mage_Index_Model_Process::MODE_MANUAL)->save();
    }
  }

  protected function syncCategories(&$status) {
    $categoryModel = Mage::getSingleton('octahedron_pos/category');
    $currentCategories = $categoryModel->currentCategories();

    $serverCategories = $this->api->categories();
    $deletedCategories = array_diff(array_keys($currentCategories), array_keys($serverCategories));
    if ($deletedCategories) {
      foreach ($deletedCategories as $deletedCategory) $categoryModel->deleteCategory($deletedCategory);
    }
    $status[] = ['key' => 'Deleted Categories', 'count' => count($deletedCategories)];

    $newCategories = array_diff(array_keys($serverCategories), array_keys($currentCategories));
    if ($newCategories) {
      foreach ($newCategories as $newCategory) $categoryModel->addCategory($newCategory);
    }
    $status[] = ['key' => 'New Categories', 'count' => count($newCategories)];

    if ($deletedCategories || $newCategories) $categoryModel->updateCategoryCount();
    $categoryModel->updateRootCategory();
  }

  protected function syncTaxValues(&$status, $lastSync) {
    $taxModel = Mage::getSingleton('octahedron_pos/tax');
    if (!$lastSync) $taxModel->clearTaxes();

    $currentTaxRates = $taxModel->currentTaxRates();
    $serverTaxValues = $this->api->taxValues();
    $deletedTaxValues = array_diff(array_keys($currentTaxRates), array_keys($serverTaxValues));
    if ($deletedTaxValues) {
      foreach ($deletedTaxValues as $deletedTaxValue) $taxModel->deleteTax($deletedTaxValue);
    }
    $status[] = ['key' => 'Deleted Tax Values', 'count' => count($deletedTaxValues)];

    $newTaxValues = array_diff(array_keys($serverTaxValues), array_keys($currentTaxRates));
    if ($newTaxValues) {
      foreach ($newTaxValues as $newTaxValue) {
        $taxModel->addTax($newTaxValue, $serverTaxValues[$newTaxValue]['value'], $serverTaxValues[$newTaxValue]['country_code']);
      }
    }
    $status[] = ['key' => 'New Tax Values', 'count' => count($newTaxValues)];
  }

  protected function syncStock(&$status) {
    $stockModel = Mage::getSingleton('octahedron_pos/stock');
    $currentStock = $stockModel->getCurrentStock();
    $updatedStock = 0;
    $newStock = 0;
    $deletedStock = 0;
    $page = 1;
    do {
      $stock = $this->api->stock($page++);
      foreach ($stock['_embedded']['stock'] as $item) {
        if (isset($currentStock[$item['stock_number']])) {
          $updatedStock += $this->updateStock($item, $currentStock[$item['stock_number']], $stockModel);
          unset($currentStock[$item['stock_number']]);
        }
        else {
          $stockModel->addStockItem($item);
          $newStock++;
        }
      }
    }
    while ($stock['end'] < $stock['total']);

    if ($currentStock) {
      $deletedStock = count($currentStock);
      $attributes = Mage::getSingleton('catalog/config')->getProductAttributes();
      $collection = Mage::getModel('catalog/product')
    			->getCollection()
    			->addAttributeToFilter('sku', array('in' => array_keys($currentStock)))
    			->addAttributeToSelect($attributes);
      foreach ($collection as $deletedProduct) $stockModel->deleteStockItem($deletedProduct);
    }
    $status[] = ['key' => 'Updated Stock', 'count' => $updatedStock];
    $status[] = ['key' => 'New Stock', 'count' => $newStock];
    $status[] = ['key' => 'Deleted Stock', 'count' => $deletedStock];
  }

  protected function updateStock($item, $localLastModified, $stockModel) {
    if (strtotime($item['date_modified']) > strtotime($localLastModified)) {
      $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $item['stock_number']);
      if ($product) {
        $stockModel->updateStockItem($product, $item);
        return 1;
      }
    }
    return 0;
  }

  protected function enableAutoIndexAndReindexData() {
    $process = Mage::getModel('index/indexer')->getProcessByCode('cataloginventory_stock');
    $process->reindexAll();
    foreach (Mage::getModel('index/process')->getCollection() as $index) {
      $index->reindexAll();
    }

    $processCollection = Mage::getSingleton('index/indexer')->getProcessesCollection();
    foreach ($processCollection as $process) {
       $process->setMode(Mage_Index_Model_Process::MODE_REAL_TIME)->save();
    }
  }

}
