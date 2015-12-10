<?php

/**
 * Pos Tax
 *
 * @category    Octahedron
 * @package     Octahedron_Pos
 * @author      andrew@octahedron.com.au
 */
class Octahedron_Pos_Model_Tax {

  protected $resource;
  protected $dbRead;
  protected $dbWrite;
  protected $taxTable;
  protected $taxRuleTable;
  protected $taxClassTable;
  protected $taxRateTable;
  protected $currentTaxClasses;
  protected $currentTaxRules;
  protected $currentTaxRates;

  public function __construct() {
    $this->resource = Mage::getSingleton('core/resource');
    $this->dbRead = $this->resource->getConnection('core_read');
    $this->dbWrite = $this->resource->getConnection('core_write');

    $this->taxTable = $this->resource->getTableName('tax_calculation');
    $this->taxRuleTable = $this->resource->getTableName('tax_calculation_rule');
    $this->taxClassTable = $this->resource->getTableName('tax_class');
    $this->taxRateTable = $this->resource->getTableName('tax_calculation_rate');

    $this->currentTaxClasses = array_reduce($this->dbRead->fetchAll('SELECT class_id, class_name FROM ' . $this->taxClassTable), function($taxClasses, $row) {
      $taxClasses[$row['class_name']] = $row['class_id'];
      return $taxClasses;
    }, []);

    $this->currentTaxRules = array_reduce($this->dbRead->fetchAll('SELECT code, tax_calculation_rule_id FROM ' . $this->taxRuleTable), function($taxRules, $row) {
      $taxRules[$row['code']] = $row['tax_calculation_rule_id'];
      return $taxRules;
    }, []);

    $this->currentTaxRates = array_reduce($this->dbRead->fetchAll('SELECT code, tax_calculation_rate_id FROM ' . $this->taxRateTable), function($taxValues, $row) {
      $taxValues[$row['code']] = $row['tax_calculation_rate_id'];
      return $taxValues;
    }, []);
  }

  public function currentTaxRates() {
    return $this->currentTaxRates;
  }

  public function currentTaxClasses() {
    return $this->currentTaxClasses;
  }

  public function addTax($tax, $value, $countryCode) {
    $taxRateQuery = 'INSERT INTO ' . $this->taxRateTable . " (tax_country_id, tax_region_id, tax_postcode, code, rate) VALUES (:countryCode, 0, '*', :code, :rate)";
    $taxRuleQuery = 'INSERT INTO ' . $this->taxRuleTable . " (code, priority, position, calculate_subtotal) VALUES (:code, 1, 1, 0)";
    $taxQuery = 'INSERT INTO ' . $this->taxTable . " (tax_calculation_rate_id, tax_calculation_rule_id, customer_tax_class_id, product_tax_class_id) VALUES (:rateId, :ruleId, :customerClassId, :productClassId)";
    $this->dbWrite->query($taxRateQuery, array('countryCode' => $countryCode, 'code' => $tax, 'rate' => $value * 100 - 100));
    $rateId = $this->dbWrite->lastInsertId();
    $this->currentTaxRates[$tax] = $rateId;
    $this->dbWrite->query($taxRuleQuery, array('code' => $tax));
    $ruleId = $this->dbWrite->lastInsertId();
    $this->currentTaxRules[$tax] = $ruleId;
    $this->dbWrite->query($taxQuery, array('rateId' => $rateId, 'ruleId' => $ruleId, 'customerClassId' => $this->currentTaxClasses['Retail Customer'], 'productClassId' => $this->currentTaxClasses['Taxable Goods']));
  }

  public function deleteTax($tax) {
    $this->dbWrite->query('DELETE FROM ' . $this->taxTable . ' WHERE tax_calculation_rate_id = :rateId AND tax_calculation_rule_id = :ruleId', array('rateId' => $this->currentTaxRates[$tax], 'ruleId' => $this->currentTaxRules[$tax]));
    $this->dbWrite->query('DELETE FROM ' . $this->taxRuleTable . ' WHERE code = :code', ['code' => $tax]);
    $this->dbWrite->query('DELETE FROM ' . $this->taxRateTable . ' WHERE code = :code', ['code' => $tax]);
  }

  public function clearTaxes() {
    $this->dbWrite->query('DELETE FROM ' . $this->taxTable);
    $this->dbWrite->query('DELETE FROM ' . $this->taxRuleTable);
    $this->dbWrite->query('DELETE FROM ' . $this->taxRateTable);
    $this->currentTaxRules = [];
    $this->currentTaxRates = [];
  }

}
