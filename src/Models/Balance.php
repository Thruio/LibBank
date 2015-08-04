<?php

namespace Thru\BankApi\Models;

use Thru\ActiveRecord\ActiveRecord;

/**
 * Class Balance
 * @var $balance_id integer
 * @var $run_id integer
 * @var $account_id integer
 * @var $value text
 * @var $created date
 * @var $updated date
 */
class Balance extends ActiveRecord{

  protected $_table = "balances";

  public $balance_id;
  public $run_id;
  public $account_id;
  public $value;
  public $created;
  public $updated;

  private $_run;

  public function save(){
    $this->updated = date("Y-m-d H:i:s");
    if(!$this->created){
      $this->created = date("Y-m-d H:i:s");
    }

    $this->value = preg_replace("/[^0-9.-]/", "", $this->value);
    $this->value = doubleval($this->value);

    parent::save();
  }

  /**
   * @return Run
   */
  public function getRun(){
    if(!$this->_run) {
      $this->_run = Run::search()->where('run_id', $this->run_id)->execOne();
    }
    return $this->_run;
  }
}
