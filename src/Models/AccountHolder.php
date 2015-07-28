<?php

namespace Thru\BankApi\Models;

use Thru\ActiveRecord\ActiveRecord;

/**
 * Class AccountHolder
 * @var $account_holder_id integer
 * @var $name text
 * @var $created date
 * @var $updated date
 */
class AccountHolder extends ActiveRecord{

  protected $_table = "account_holders";

  public $account_holder_id;
  public $name;
  public $created;
  public $updated;

  public function save(){
    $this->updated = date("Y-m-d H:i:s");
    if(!$this->created){
      $this->created = date("Y-m-d H:i:s");
    }
    parent::save();
  }
}
