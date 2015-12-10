<?php
/**
 * @category    Octahedron
 * @package     Octahedron_Pos
 * @author      andrew@octahedron.com.au
 */
class Octahedron_Pos_Block_Sync extends Mage_Adminhtml_Block_Template {

  public function setScriptPath($dir) {
    $scriptPath = realpath($dir);
    if (strpos($scriptPath, realpath(Mage::getBaseDir('design'))) === 0 ||
        strpos($scriptPath, realpath(Mage::getModuleDir('', 'Octahedron_Pos'))) === 0 ||
        $this->_getAllowSymlinks()) {
      $this->_viewDir = $dir;
    }
    else Mage::log('Not valid script path:' . $dir, Zend_Log::CRIT, null, null, true);
    return $this;
  }

  public function fetchView($fileName) {
		$path = Mage::getModuleDir('', 'Octahedron_Pos');
		$this->setScriptPath($path . '/templates');
		return parent::fetchView($this->getTemplate());
	}

}
