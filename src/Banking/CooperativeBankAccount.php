<?php
namespace Thru\BankApi\Banking;

use Thru\BankApi\Models\Account;
use Thru\BankApi\Models\AccountHolder;
use Thru\BankApi\Models\Balance;
use Thru\BankApi\Models\Run;
use Thru\BankApi\Models\Transaction;

class CooperativeBankAccount extends BaseBankAccount {
  protected $baseUrl = "https://personal.co-operativebank.co.uk/CBIBSWeb/start.do";

  public function __construct($accountName){
    parent::__construct($accountName);
  }

  public function run(AccountHolder $accountHolder, Run $run){
    parent::run($accountHolder, $run);
    $this->getSelenium()->get($this->baseUrl);
    if($this->getAuth('sort') && $this->getAuth('acct')){
      $this->getSelenium()->findElement(\WebDriverBy::id("sortcode"))->clear()->sendKeys($this->getAuth('sort'));
      $this->getSelenium()->findElement(\WebDriverBy::id("accountnumber"))->clear()->sendKeys($this->getAuth('acct'));
    }elseif($this->getAuth('creditcard')){
      $this->getSelenium()->findElement(\WebDriverBy::id("visanumber"))->clear()->sendKeys($this->getAuth('creditcard'));
    }else{
      throw new BankAccountAuthException("No 'sort' and 'acct' given, nor was a 'creditcard'");
    }
    $this->takeScreenshot("Identity");
    $this->getSelenium()->findElement(\WebDriverBy::name('ok'))->click();

    $words = ['first' => 0,'second' => 1,'third' => 2,'fourth' => 3];
    $pinBytes = str_split($this->getAuth('security'), 1);

    sleep(2);

    $firstPinByteIdentifier  = strtolower($this->getSelenium()->findElement(\WebDriverBy::cssSelector("label[for='firstPassCodeDigit']"))->getText());
    $secondPinByteIdentifier = strtolower($this->getSelenium()->findElement(\WebDriverBy::cssSelector("label[for='secondPassCodeDigit']"))->getText());

    $firstPinByteIdentifier  = trim(str_replace("digit", "", trim($firstPinByteIdentifier)));
    $secondPinByteIdentifier = trim(str_replace("digit", "", trim($secondPinByteIdentifier)));

    $firstDigitSelect = $this->getSelenium()->findElement(\WebDriverBy::id('firstPassCodeDigit'));
    $secondDigitSelect = $this->getSelenium()->findElement(\WebDriverBy::id('secondPassCodeDigit'));

    $firstIndex = $words[$firstPinByteIdentifier];
    $secondIndex = $words[$secondPinByteIdentifier];

    $firstNumber = $pinBytes[$firstIndex];
    $secondNumber = $pinBytes[$secondIndex];

    $firstDigitSelect->findElement(\WebDriverBy::cssSelector("option[value='" . $firstNumber . "']"))->click();
    $secondDigitSelect->findElement(\WebDriverBy::cssSelector("option[value='" . $secondNumber . "']"))->click();

    echo "Selected Security PIN digit was {$firstPinByteIdentifier} and equals {$firstNumber}\n";
    echo "Selected Security PIN digit was {$secondPinByteIdentifier} and equals {$secondNumber}\n";

    $this->takeScreenshot("Security");
    $this->getSelenium()->findElement(\WebDriverBy::name('ok'))->click();

    // Memorable Date?
    try{
      $challengeMemorableDay = $this->getSelenium()->findElement(\WebDriverBy::name("memorableDay"));
      $challengeMemorableMonth = $this->getSelenium()->findElement(\WebDriverBy::name("memorableMonth"));
      $challengeMemorableYear = $this->getSelenium()->findElement(\WebDriverBy::name("memorableYear"));
      $challengeMemorableDay->clear()->sendKeys(date('d', strtotime($this->getAuth('memorable_date'))));
      $challengeMemorableMonth->clear()->sendKeys(date('m', strtotime($this->getAuth('memorable_date'))));
      $challengeMemorableYear->clear()->sendKeys(date('Y', strtotime($this->getAuth('memorable_date'))));
      echo "Memorable date is " . $challengeMemorableDay->getAttribute('value') . " / " .$challengeMemorableMonth->getAttribute('value') . " / " . $challengeMemorableYear->getAttribute('value') . "\n";

    }catch(\NoSuchElementException $e){
      // Do nothing
    }

    // First School?
    try{
      $challengeFirstSchool = $this->getSelenium()->findElement(\WebDriverBy::name("firstSchool"));
      $challengeFirstSchool->clear()->sendKeys($this->getAuth('first_school'));
      echo "First school is {$challengeFirstSchool->getAttribute('value')}\n";
    }catch(\NoSuchElementException $e){
      // Do nothing
    }

    // Last School?
    try{
      $challengeLastSchool = $this->getSelenium()->findElement(\WebDriverBy::name("lastSchool"));
      $challengeLastSchool->clear()->sendKeys($this->getAuth('last_school'));
      echo "Last school is {$challengeLastSchool->getAttribute('value')}\n";
    }catch(\NoSuchElementException $e){
      // Do nothing
    }

    // Birthplace?
    try{
      $challengeBirthPlace = $this->getSelenium()->findElement(\WebDriverBy::name("birthPlace"));
      $challengeBirthPlace->clear()->sendKeys($this->getAuth('birth_place'));
      echo "Birthplace is {$challengeBirthPlace->getAttribute('value')}\n";
    }catch(\NoSuchElementException $e){
      // Do nothing
    }

    // Birthplace?
    try{
      $challengeMemorableName = $this->getSelenium()->findElement(\WebDriverBy::name("memorableName"));
      $challengeMemorableName->clear()->sendKeys($this->getAuth('memorable_name'));
      echo "Memorable Name is {$challengeMemorableName->getAttribute('value')}\n";
    }catch(\NoSuchElementException $e){
      // Do nothing
    }

    $this->takeScreenshot("Challenge");
    $this->getSelenium()->findElement(\WebDriverBy::name('ok'))->click();
    $this->takeScreenshot("Logged in");

    try{
      $errors = $this->getSelenium()->findElements(\WebDriverBy::cssSelector('.error'));
      if(count($errors) > 0) {
        $errorsMessages = '';
        foreach ($errors as $error) {
          $errorsMessages .= trim($error->getText()) . "\n";
        }
        throw new BankAccountAuthException("Failed to log in. See Logged-in screenshot. {$errorsMessages}");
      }
    }catch(\NoSuchElementException $e){
      // Do nothing, everything is ok
    }

    // Check for an "important message"
    try{
      $this->getSelenium()->findElement(\WebDriverBy::name('ok'))->click();
    }catch(\NoSuchElementException $e){
      // Do nothing, everything is ok
    }

    // Get Balances
    $accountsTable = $this->getSelenium()->findElement(\WebDriverBy::cssSelector("td.verttop:nth-child(2) > table:nth-child(1) > tbody:nth-child(1) > tr:nth-child(1) > td:nth-child(1) > table:nth-child(1) > tbody:nth-child(1) > tr:nth-child(5) > td:nth-child(1) > table:nth-child(1)"));
    $accountsTableRows = $accountsTable->findElements(\WebDriverBy::cssSelector("tr"));
    $accountsTableRows = array_slice($accountsTableRows,1);
    $accountsToCheck = [];

    foreach($accountsTableRows as $accountsTableRow){
      $tds = $accountsTableRow->findElements(\WebDriverBy::cssSelector("td"));

      $accountName = $tds[0]->getText();
      $accountBalance = $tds[1]->getText();

      $accountBalance = str_replace("£", "", $accountBalance);
      $polarity = substr($accountBalance,-1,1);
      $accountBalance = $polarity . substr($accountBalance, 0, -1);
      $accountBalance = doubleval($accountBalance);

      $accountSortCodeAndAccountNumber = explode(" ", $tds[2]->getText(),2);
      if(count($accountSortCodeAndAccountNumber) == 2) {
        $accountSortCode = trim($accountSortCodeAndAccountNumber[0]);
        $accountAccountNumber = trim($accountSortCodeAndAccountNumber[1]);
        $accountNameDisplay = "{$accountName} ($accountSortCode $accountAccountNumber)";
      }else{
        $accountAccountNumber = $accountSortCodeAndAccountNumber[0];
        $accountNameDisplay = "{$accountName} ($accountAccountNumber)";
      }

      $account = Account::FetchOrCreateByName($accountHolder, $accountNameDisplay);
      $balance = new Balance();
      $balance->run_id = $run->run_id;
      $balance->account_id = $account->account_id;
      $balance->value = $accountBalance;
      $balance->save();

      echo "Balance for {$accountNameDisplay} is {$balance->value}\n";
      $accountsToCheck[] = ['name' => $accountName, 'account' => $account];
    }

    // Get Transactions
    foreach($accountsToCheck as $accountData){
      $accountName = $accountData['name'];
      $account = $accountData['account'];
      $accountsTable = $this->getSelenium()->findElement(\WebDriverBy::cssSelector("td.verttop:nth-child(2) > table:nth-child(1) > tbody:nth-child(1) > tr:nth-child(1) > td:nth-child(1) > table:nth-child(1) > tbody:nth-child(1) > tr:nth-child(5) > td:nth-child(1) > table:nth-child(1)"));
      $accountsTableRows = $accountsTable->findElements(\WebDriverBy::cssSelector("tr"));
      $accountsTableRows = array_slice($accountsTableRows,1);
      // Navigate to transaction log
      foreach($accountsTableRows as $accountsTableRow) {
        $links = $this->getSelenium()->findElements(\WebDriverBy::cssSelector("a[title='click here to go to recent items']"));

        foreach($links as $link) {
          if ($link->getText() == $accountName) {
            $link->click();
            break;
          }
        }
      }

      unset($accountsTable, $accountsTableRow, $link);

      // Get transaction log table

      try {
        $summaryTable = $this->getSelenium()->findElement(\WebDriverBy::className("summaryTable"));
      }catch(\NoSuchElementException $e){
        // Supress.
        $summaryTable = false;
      }
      if($summaryTable) {
        $rows = $summaryTable->findElements(\WebDriverBy::cssSelector("tbody tr"));
        $rows = array_slice($rows, 1, count($rows) - 2);
        foreach ($rows as $row) {
          $cells = $row->findElements(\WebDriverBy::tagName("td"));
          $transactionDate = date("Y-m-d H:i:s", strtotime(trim($cells[0]->getText())));
          $transactionMerchant = trim($cells[1]->getText());
          $transactionIn = doubleval(preg_replace("/[^0-9,.]/", "", trim($cells[2]->getText())));
          if(isset($cells[3])) {
            $transactionOut = doubleval(preg_replace("/[^0-9,.]/", "", trim($cells[3]->getText())));
          }else{
            $transactionOut = 0;
          }

          $transaction = Transaction::Create($run, $account, $transactionMerchant, $transactionDate, $transactionIn - $transactionOut);

          echo " > Added Transaction: {$transaction->name} {$transaction->value}\n";
        }
      }

      $account->last_check = date("Y-m-d H:i:s");
      $account->save();

      // Back
      $menu = $this->getSelenium()->findElement(\WebDriverBy::cssSelector(".subHeadOuter .subHead .subNav"));
      $buttons = $menu->findElements(\WebDriverBy::tagName("a"));
      foreach($buttons as $button){
        if($button->getText() == "home"){
          $button->click();
          break;
        }
      }
    }
  }
}