<?php

/**
 * Pos loader
 *
 * @category    Octahedron
 * @package     Octahedron_Pos
 * @author      andrew@octahedron.com.au
 */
class Octahedron_Pos_Model_Loader {

  public function controllerFrontInitBefore(Varien_Event_Observer $observer) {
    self::init();
  }

  public static function init() {
    $vendorPath = __DIR__ . DS . '..' . DS . 'vendor';
    set_include_path(get_include_path() . PATH_SEPARATOR . $vendorPath);
    require_once($vendorPath . DS . 'autoload.php');
  }

}
