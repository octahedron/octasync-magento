<?php

/**
 * Pos Customer
 *
 * @category    Octahedron
 * @package     Octahedron_Pos
 * @author      andrew@octahedron.com.au
 */
class Octahedron_Pos_Model_Customer {

  protected $api;

  public function __construct() {
    $this->api = Mage::helper('octahedron_pos/api');
  }

  public function createRemoteCustomer(Varien_Event_Observer $observer) {
    try {
      $customer = $observer->getEvent()->getCustomer();
      Mage::log('Creating external customer link for #' . $customer->getId(), Zend_Log::INFO);
      $remoteCustomer = $this->api->saveCustomer([
        'id' => $customer->getId(),
        'firstName' => $customer->getFirstname(),
        'lastName' => $customer->getLastname(),
        'email' => $customer->getEmail(),
        'isActive' => true
      ]);
      Mage::log('Created external customer link: ' . $customer->getId() . ' -> ' . $remoteCustomer['id'], Zend_Log::INFO);
    }
    catch (Exception $e) {
      Mage::logException($e);
    }
  }

  public function updateRemoteCustomer(Varien_Event_Observer $observer) {
    if (Mage::registry('Update Remote Customer')) return;
    try {
      $customer = $observer->getEvent()->getCustomerAddress()->getCustomer();
      $address = $customer->getPrimaryBillingAddress();
      $street = $address->getStreet();
      Mage::log('Updating external customer #' . $customer->getId(), Zend_Log::INFO);
      $remoteCustomer = $this->api->saveCustomer([
        'id' => $customer->getId(),
        'firstName' => $customer->getFirstname(),
        'lastName' => $customer->getLastname(),
        'email' => $customer->getEmail(),
        'isActive' => true,
        'homePhone' => $address->getTelephone(),
        'faxPhoneNumber' => $address->getFax(),
        'street' => $street[0],
        'suburb' => count($street) > 1 ? $street[1] : null,
        'city' => $address->getCity(),
        'state' => $address->getRegion(),
        'postcode' => $address->getPostcode(),
        'country' => $address->getCountry()
      ]);
      Mage::log('Updated external customer #' . $customer->getId(), Zend_Log::INFO);
    }
    catch (Exception $e) {
      Mage::logException($e);
    }
    Mage::register('Update Remote Customer', true);
  }

}
