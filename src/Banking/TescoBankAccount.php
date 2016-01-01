<?php
namespace Thru\BankApi\Banking;

use Thru\BankApi\Models\Account;
use Thru\BankApi\Models\AccountHolder;
use Thru\BankApi\Models\Balance;
use Thru\BankApi\Models\Run;
use Thru\BankApi\Models\Transaction;

class TescoBankAccount extends BaseBankAccount
{
    protected $baseUrl = "https://www.tescobank.com/sss/auth";

    public function __construct($accountName)
    {
        parent::__construct($accountName);
    }

    public function run(AccountHolder $accountHolder, Run $run, $accountLabel)
    {
        if (!parent::run($accountHolder, $run, $accountLabel)) {
            return false;
        }
        $this->getSelenium()->get($this->baseUrl);

        sleep(3);

        $this->getSelenium()->findElement(\WebDriverBy::id("login-uid"))->clear()->sendKeys($this->getAuth('username'));
        $this->takeScreenshot("username typed");
        $this->getSelenium()->findElement(\WebDriverBy::id('login-uid-submit-button'))->click();
        $this->takeScreenshot("username submitted");

        sleep(3);
        $this->takeScreenshot("looking for pamphrase");

        $pamPhrase = trim($this->getSelenium()->findElement(\WebDriverBy::id('PAMPhrase'))->getText());
        if(!$pamPhrase == $this->getAuth('codephrase')){
            throw new \Exception("Codephrase was found to be bad. Used {$this->getAuth()}, found {$pamPhrase}");
        }

        for($i = 1; $i <= 6; $i++) {
            $digit = $this->getSelenium()->findElement(\WebDriverBy::id('DIGIT' . $i));
            if($digit->getAttribute('disabled')){
                // Skip it
            }else{
                $digit->clear()->sendKeys(substr($this->getAuth('security'), $i - 1, 1));
            }
        }
        $this->takeScreenshot("pin typed");
        $this->getSelenium()->findElement(\WebDriverBy::id('NEXTBUTTON'))->click();
        $this->takeScreenshot("pin submitted");

        // check to see if we get a "Just so we can be sure its you" dialog
        $headingText = $this->getSelenium()->findElement(\WebDriverBy::cssSelector('h1.page-heading__primary'))->getText();
        if($headingText == 'Just so we can check it\'s you..'){
            $this->getSelenium()->findElement(\WebDriverBy::cssSelector('button.js-submit'))->click();
            $this->takeScreenshot("otp challenge");
            $otp = readline("Please enter Tesco OTP: ");
            if($otp){
                $this->getSelenium()->findElement(\WebDriverBy::name('OTP'))->clear()->sendKeys($otp);
                $this->getSelenium()->findElement(\WebDriverBy::id('NEXTBUTTON'))->click();
                $this->takeScreenshot("otp submitted");
            }else{
                echo "Check your phone for a one time access code!\n";
                echo "You need to store this as 'otp' in configuration.yml\n";
                return false;
            }
        }

        // Password time.
        $this->getSelenium()->findElement(\WebDriverBy::id('PASSWORD'))->clear()->sendKeys($this->getAuth('password'));
        $this->getSelenium()->findElement(\WebDriverBy::cssSelector('label.form__radio__label[for=DOWNLOADAID_Y]'))->click();
        $this->takeScreenshot("password entered");
        $this->getSelenium()->findElement(\WebDriverBy::id('NEXTBUTTON'))->click();
        $this->takeScreenshot("password submitted");

        // wait for the stupid javascript shit tesco does.
        sleep(10);

        $this->takeScreenshot("statement");

        // Get balances
        $products = $this->getSelenium()->findElements(\WebDriverBy::cssSelector('.product'));
        foreach($products as $product){
            $productName = trim($product->findElement(\WebDriverBy::cssSelector('h2.product-name'))->getText());
            $availableBalance = trim($product->findElement(\WebDriverBy::cssSelector('.detail-content dl.summary-detail.account-value dd.available-balance'))->getText());
            $sortCode = trim($product->findElement(\WebDriverBy::cssSelector('.detail-content dl.summary-detail dd.sort-code'))->getText());
            $accountNumber = trim($product->findElement(\WebDriverBy::cssSelector('.detail-content dl.summary-detail dd.account-number'))->getText());

            // Filter out currency symbols
            $availableBalance = trim(str_replace("£", '', $availableBalance));

            // Generate a nice display name
            $accountNameDisplay = "{$productName} ({$sortCode} {$accountNumber})";
            echo "Balance for '{$accountNameDisplay}' is' {$availableBalance}'\n";

            // Store balance
            $account = Account::FetchOrCreateByName($accountHolder, $accountNameDisplay);
            $balance = new Balance();
            $balance->run_id = $run->run_id;
            $balance->account_id = $account->account_id;
            $balance->value = $account->balance_inverted == "Yes" ? $availableBalance * -1 : $availableBalance;
            $balance->save();
        }

        // Log out.
        $this->getSelenium()->findElement(\WebDriverBy::id('logout-button'))->click();
    }
}