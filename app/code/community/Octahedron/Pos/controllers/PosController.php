<?php

class Octahedron_Pos_PosController extends Mage_Adminhtml_Controller_Action {

  public function indexAction() {
    $this->loadLayout();
    $this->_setActiveMenu('octahedron');
    $this->_addContent($this->getLayout()->createBlock('octahedron_pos/sync')->setTemplate('index.phtml'));
    $this->renderLayout();
  }

  public function syncAction() {
    Mage::helper('octahedron_pos/sync')->run();
  }

}
