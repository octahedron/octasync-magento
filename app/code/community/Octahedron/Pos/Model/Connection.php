<?php

use League\OAuth2\Client\Exception\IDPException;
use Guzzle\Http\Exception\BadResponseException;

/**
 * Pos model
 *
 * @category    Octahedron
 * @package     Octahedron_Pos
 * @author      andrew@octahedron.com.au
 */
class Octahedron_Pos_Model_Connection extends Mage_Core_Model_Config_Data {

  public function save() {
    $fields = $_POST['groups']['connection']['fields'];
    $url = $fields['url']['value'];
    $clientId = $fields['client_id']['value'];
    $clientSecret = $fields['client_secret']['value'];

    $api = Mage::helper('octahedron_pos/api');
    $api->setClientCredentials($url, $clientId, $clientSecret);
    $token = $api->getAccessToken('client-credentials');
    Mage::getConfig()->saveConfig('octahedron_pos/api/token', $token);

    return parent::save();
  }

}
