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

  private $_accounts;

  public function save($automatic_reload = true){
    $this->updated = date("Y-m-d H:i:s");
    if(!$this->created){
      $this->created = date("Y-m-d H:i:s");
    }
    parent::save($automatic_reload);
  }

  /**
   * @param $name
   * @return AccountHolder
   */
  static public function FetchOrCreateByName($name){
    $accountHolder = AccountHolder::factory()
      ->search()
      ->where('name', $name)
      ->execOne();
    if(!$accountHolder){
      $accountHolder = new AccountHolder();
      $accountHolder->name = $name;
      $accountHolder->save();
    }
    return $accountHolder;
  }

  /**
   * @return Account[]
   */
  public function getAccounts(){
    if(!$this->_accounts){
      $this->_accounts = Account::search()->where('account_holder_id', $this->account_holder_id)->exec();
    }
    return $this->_accounts;
  }
}
