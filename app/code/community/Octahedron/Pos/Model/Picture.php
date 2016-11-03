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
    $localProduct->save();
  }

  protected function savePictureLocally($localProduct, $url, $type) {
    $path = Mage::getBaseDir('media') . '/import/'. basename($url);
    copy($url, $path);
    if (file_exists($path)) $localProduct->addImageToMediaGallery($path, $type, true, $type === 'small_image');
  }

  public function updateStockPicture($localProduct, $path) {
    $localProduct->setData('image', "/{$path{0}}/{$path{1}}/{$path}");
    $localProduct->setData('small_image', "/{$path{0}}/{$path{1}}/{$path}.png");
    $localProduct->save();
  }

  public function deleteStockPicture($localProduct, $path) {
    $id = $localProduct->getId();
    $mediaApi = Mage::getModel('catalog/product_attribute_media_api');
    $mediaApi->remove($id, "/{$path{0}}/{$path{1}}/{$path}");
    $mediaApi->remove($id, "/{$path{0}}/{$path{1}}/{$path}.png");
    // $localProduct->save();
  }

}
