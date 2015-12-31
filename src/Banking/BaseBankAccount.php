<?php
namespace Thru\BankApi\Banking;

use Thru\BankApi\Models\Account;
use Thru\BankApi\Models\AccountHolder;
use Thru\BankApi\Models\Run;

class BaseBankAccount {
  protected $auth;
  protected $selenium;
  private $screenshotCount;
  private $accountName;
  private $accountLabel;

  protected $check_minimum_interval = "15 minutes ago";

  public function setAuth($auth){
    $this->auth = $auth;
    return $this;
  }
  public function getAuth($aspect){
    if(isset($this->auth[$aspect])){
      return $this->auth[$aspect];
    }
    return false;
  }

  public function setSelenium(\RemoteWebDriver $selenium){
    $this->selenium = $selenium;
    return $this;
  }

  /**
   * @return \RemoteWebDriver
   */
  public function getSelenium(){
    return $this->selenium;
  }

  public function takeScreenshot($name){
    $this->screenshotCount++;
    $name = "{$this->getAccountName()}-{$this->screenshotCount}-{$name}";
    $name = str_replace(" ", "-", $name);
    $this->getSelenium()->takeScreenshot(APP_ROOT . "/screenshots/{$name}.png");
  }

  public function run(AccountHolder $accountHolder, Run $run, $accountLabel){
    $this->accountLabel = $accountLabel;
    $account = Account::FetchOrCreateByName($accountHolder, $this->getAccountName());

    # Prevent checking the balance too often
    if(strtotime($account->last_check) >= strtotime($this->check_minimum_interval)){
      echo "Last checked less than {$this->check_minimum_interval}... Skipping\n";
      return false;
    }

    return true;
  }

  public function cleanUp(){
  }

  public function __construct($accountName){
    $this->accountName = $accountName;
  }

  public function getAccountName(){
    return $this->accountLabel . " - " . $this->accountName;
  }


}