<?php

/**
 * Pos Sale
 *
 * @category    Octahedron
 * @package     Octahedron_Pos
 * @author      andrew@octahedron.com.au
 */
class Octahedron_Pos_Model_Sale {

  protected $api;

  public function __construct() {
    $this->api = Mage::helper('octahedron_pos/api');
  }

  protected function getPaymentType($payment) {
    if ($payment->getMethod() === 'ccsave') {
      switch ($payment->getCcType()) {
        case 'VI': return 'Visa';
        case 'MC': return 'Mastercard';
        case 'AE': return 'Amex';
        case 'DI': return 'Diners';
      }
    }
    return 'Cheque';
  }

  public function createRemoteSale(Varien_Event_Observer $observer) {
    try {
      $order = $observer->getEvent()->getOrder();
      $customer = null;
      if (!$order->getCustomerIsGuest()) {
        $customer = [
          'id' => $order->getCustomerId(),
          'birthDate' => $order->getCustomerDob(),
          'email' => $order->getCustomerEmail(),
          'firstName' => $order->getCustomerFirstname(),
          'lastName' => $order->getCustomerLastname(),
          'sex' => $order->getCustomerGender()
        ];
      }
      $items = array_map(function($item) {
        return ['stockNumber' => $item->getSku(), 'qty' => $item->getQtyOrdered(), 'price' => $item->getBasePriceInclTax()];
      }, $order->getAllItems());
      if ($order->getShippingAmount() > 0) {
        $items[] = ['nonStockDescription' => 'Shipping', 'qty' => 1, 'price' => $order->getShippingAmount()];
      }

      $payments = array_map(function($payment) {
        return ['amount' => $payment->getAmountOrdered(), 'type' => $this->getPaymentType($payment)];
      }, $order->getAllPayments());

      Mage::log('Creating external sale link for #' . $order->getId(), Zend_Log::INFO);
      $sale = $this->api->createSale($items, $payments, $customer);
      $order->setPosSaleId($sale['id']);
      Mage::log('Created external sale link: ' . $order->getId() . ' -> ' . $sale['id'], Zend_Log::INFO);
    }
    catch (Exception $e) {
      Mage::logException($e);
    }
  }

}
