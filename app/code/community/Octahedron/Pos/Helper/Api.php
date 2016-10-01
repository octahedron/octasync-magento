<?php

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Entity\User;
use League\OAuth2\Client\Token\AccessToken as AccessToken;
use Guzzle\Http\Exception\BadResponseException;
use League\OAuth2\Client\Exception\IDPException as IDPException;

/**
 * Octahedron API
 *
 * @category    Octahedron
 * @package     Octahedron_Pos
 * @author      andrew@octahedron.com.au
 */
class Octahedron_Pos_Helper_Api extends AbstractProvider {

  const TOKEN_KEY = 'octahedron_pos/api/token';

  public $authorizationHeader = 'Bearer';
  protected $url;
  protected $cache;

  public function __construct() {
    parent::__construct([
      'clientId' => Mage::getStoreConfig('octahedron_pos/connection/client_id'),
      'clientSecret' => Mage::getStoreConfig('octahedron_pos/connection/client_secret')
    ]);
    $this->url = 'https://' . Mage::getStoreConfig('octahedron_pos/connection/url');
    $this->headers['Accept'] = 'application/json';
    $this->cache = Mage::app()->getCache();
  }

  public function getAccessToken($grant = 'client-credentials', $params = []) {
    $token = unserialize($this->cache->load(self::TOKEN_KEY));
    if (!$token) {
      $token = parent::getAccessToken($grant, $params);
      $this->cache->save(serialize($token), self::TOKEN_KEY, [], $token->expires - time());
    }
    return $token;
  }

  public function setClientCredentials($url, $clientId, $clientSecret) {
    $this->url = 'https://' . $url;
    $this->clientId = $clientId;
    $this->clientSecret = $clientSecret;
    $this->cache->remove(self::TOKEN_KEY);
  }

  public function urlAccessToken() {
    return $this->url . '/access_token';
  }

  public function stock($page = 1, $stockNumber = false) {
    $url = $this->url . '/stock?_limit=1000&_order=stockNumber&_dir=asc&_page=' . $page;
    if ($stockNumber) $url .= '&stockNumber=' . $stockNumber;
    return $this->fetchData($url);
  }

  public function categories() {
    $response = $this->fetchData($this->url . '/categories');
    return array_reduce($response['categories'], function($categories, $row) {
      $categories[$row['category']] = $row['id'];
      return $categories;
    }, []);
  }

  public function createSale(array $items, array $payments, $customer) {
    $data = [
      'items' => $items,
      'payments' => $payments
    ];
    if ($customer) $data['customer'] = $customer;
    return $this->postData($this->url . '/sales', json_encode($data));
  }

  public function saveCustomer($customer) {
    return $this->postData($this->url . '/customers', json_encode($customer));
  }

  protected function postData($url, $data) {
    try {
      $client = $this->getHttpClient();
      $headers = $this->getDefaultHeaders($this->getAccessToken());
      $headers['Content-Type'] = 'application/json';
      $request = $client->post($url, $headers, $data)->send();
      Mage::log('Post response: ' . $request->getBody(), Zend_Log::DEBUG);
      $response = json_decode($request->getBody(), true);
    }
    catch (BadResponseException $e) {
      $response = $e->getResponse()->getBody();
      $result = $this->prepareResponse($response);
      throw new IDPException($result);
    }
    return $response;
  }

  public function getDefaultHeaders($token = null) {
    $headers = parent::getHeaders($token);
    $headers['Accept'] = 'application/hal+json';
    return $headers;
  }

  protected function fetchData($url) {
    $headers = $this->getDefaultHeaders($this->getAccessToken());
    return json_decode($this->fetchProviderData($url, $headers), true);
  }

  public function urlAuthorize() {}
  public function urlUserDetails(AccessToken $token) {}
  public function userDetails($response, AccessToken $token) {}
  public function userUid($response, AccessToken $token) {}
  public function userEmail($response, AccessToken $token) {}
  public function userScreenName($response, AccessToken $token) {}

}
