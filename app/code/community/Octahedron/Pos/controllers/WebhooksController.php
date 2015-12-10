<?php

ignore_user_abort(true);

class Octahedron_Pos_WebhooksController extends Mage_Core_Controller_Front_Action {

  protected $api;

  public function indexAction() {
    $this->api = Mage::helper('octahedron_pos/api');
    $request = $this->getRequest();
    if (!$request->isPost() || !($event = $this->getData($request))) {
      $this->norouteAction();
      return;
    }

    Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

    $dbWrite = Mage::getSingleton('core/resource')->getConnection('core_write');
    $dbWrite->beginTransaction();
    try {
      switch ($event['event']) {
        case 'stock_update': $this->updateStockItem($event['data']['stockNumber']); break;
        case 'stock_create': $this->addStockItem($event['data']['stockNumber']); break;
        case 'stock_delete': $this->deleteStockItem($event['data']['stockNumber']); break;
        case 'tax_create': $this->addTax($event['data']); break;
        case 'tax_delete': $this->deleteTax($event['data']['tax']); break;
        case 'category_update': $this->updateCategory($event['data']); break;
        case 'category_create': $this->addCategory($event['data']['category']); break;
        case 'category_merge': $this->mergeCategory($event['data']); break;
        default: throw new Exception('Invalid web hook event', 400);
      }
      $dbWrite->commit();
    }
    catch (Exception $e) {
      $dbWrite->rollback();
      $this->getResponse()
          ->setHttpResponseCode($e->getCode() ? $e->getCode() : 500)
          ->setHeader('Content-Type', 'application/json')
          ->appendBody(json_encode(array('error' => $e->getMessage())));
    }
    Mage::app()->cleanCache();
  }

  protected function getData($request) {
    $rawBody = $request->getRawBody();
    if (!trim($rawBody)) return false;
    $contentType = $request->getHeader('Content-Type');
    if (strstr($contentType, 'application/json')) return Zend_Json::decode($rawBody);
    if (strstr($contentType, 'application/xml')) return (new Zend_Config_Xml($rawBody))->toArray();
    return false;
  }

  protected function updateStockItem($stockNumber) {
    if (!preg_match('/^[A-Z]\d+$/', $stockNumber)) {
      throw new Exception('Invalid stock number', 400);
    }
    $results = $this->api->stock(1, $stockNumber);
    $remoteStockDetails = array_pop($results['_embedded']['stock']);
    if (!$remoteStockDetails) throw new Exception('Invalid stock item', 500);
    $localProduct = Mage::getModel('catalog/product')->loadByAttribute('sku', $stockNumber);
    if (strtotime($remoteStockDetails['date_modified']) > strtotime($localProduct->getUpdatedAt())) {
      Mage::getSingleton('octahedron_pos/stock')->updateStockItem($localProduct, $remoteStockDetails);
    }
    else Mage::log('Product Update Skipped (modified time older than local): ' . $localProduct->getSku(), Zend_Log::INFO);
  }

  protected function addStockItem($stockNumber) {
    $results = $this->api->stock(1, $stockNumber);
    $remoteStockDetails = array_pop($results['_embedded']['stock']);
    if (!$remoteStockDetails) throw new Exception('Invalid stock item', 500);
    Mage::getSingleton('octahedron_pos/stock')->addStockItem($remoteStockDetails);
  }

  protected function deleteStockItem($stockNumber) {
    $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $stockNumber);
    if (!$product) throw new Exception('Invalid stock item', 500);
    Mage::getSingleton('octahedron_pos/stock')->deleteStockItem($product);
  }

  protected function addTax($taxDetails) {
    Mage::getSingleton('octahedron_pos/stock')->addTax($taxDetails['tax'], $taxDetails['value'], $taxDetails['countryCode']);
  }

  protected function deleteTax($tax) {
    Mage::getSingleton('octahedron_pos/stock')->deleteTax($tax);
  }

  protected function updateCategory($categoryDetails) {
    $category = Mage::getModel('catalog/category')->loadByAttribute('name', $categoryDetails['oldCategory']);
    if (!$category) throw new Exception('Invalid category', 500);
    Mage::getSingleton('octahedron_pos/category')->updateCategory($category, $categoryDetails['newCategory']);
  }

  protected function addCategory($categoryName) {
    Mage::getSingleton('octahedron_pos/category')->addCategory($categoryName);
    $categoryModel->updateCategoryCount();
  }

  protected function mergeCategory($categoryDetails) {
    $category = Mage::getModel('catalog/category')->loadByAttribute('name', $categoryDetails['category']);
    if (!$category) throw new Exception('Invalid category', 500);
    Mage::getSingleton('octahedron_pos/category')->mergeCategory($category, $categoryDetails['mergedFrom']);
    $categoryModel->updateRootCategory();
  }

}
