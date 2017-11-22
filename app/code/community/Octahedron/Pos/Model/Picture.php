<?php

/**
 * Pos Picture
 *
 * @category    Octahedron
 * @package     Octahedron_Pos
 * @author      andrew@octahedron.com.au
 */
class Octahedron_Pos_Model_Picture {

  public function addStockPicture($localProduct, $imageUris) {
    $this->savePictureLocally($localProduct, $imageUris['base'], 'image');
    $this->savePictureLocally($localProduct, $imageUris['small'], 'small_image');
  }

  protected function savePictureLocally($localProduct, $url, $type) {
    Mage::log('Saving Image for ' . $localProduct->getSku(), Zend_Log::INFO);
    $path = Mage::getBaseDir('media') . '/import/'. basename($url);
    copy($url, $path);
    if (file_exists($path)) {
      if (!$localProduct->getMediaGallery()) {
        $localProduct->setMediaGallery(array('images' => array(), 'values' => array()));
      }
      $localProduct->addImageToMediaGallery($path, $type, true, $type === 'small_image');
      $localProduct->save();
      Mage::log('Successfully copied ' . $url . ' to ' . $path, Zend_Log::INFO);
    }
    else Mage::log('Failed copying ' . $url . ' to ' . $path, Zend_Log::INFO);
    Mage::log('End Saving Image for ' . $localProduct->getSku(), Zend_Log::INFO);
  }

  public function updateStockPicture($localProduct, $path) {
    $localProduct->setData('image', "/{$path{0}}/{$path{1}}/{$path}");
    $localProduct->setData('small_image', "/{$path{0}}/{$path{1}}/{$path}.png");
    $localProduct->save();
  }

  public function deleteStockPicture($localProduct, $path) {
    $id = $localProduct->getId();
    $mediaApi = Mage::getModel('catalog/product_attribute_media_api');
    try {
      $mediaApi->remove($id, "/{$path{0}}/{$path{1}}/{$path}");
    }
    catch (Exception $e) {}
    try {
      $mediaApi->remove($id, "/{$path{0}}/{$path{1}}/{$path}.png");
    }
    catch (Exception $e) {}
  }

}
