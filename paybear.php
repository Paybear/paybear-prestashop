<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

include_once 'classes/PaybearData.php';

class PayBear extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'paybear';
        $this->tab = 'payments_gateways';
        $this->version = '0.5.1';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'PayBear';
        $this->controllers = array('validation', 'currencies', 'payment', 'callback', 'status');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Crypto Payments by PayBear.io');
        $this->description = $this->l('Allows to accept crypto payments such as Bitcoin (BTC) and Ethereum (ETH)');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    public function install()
    {
        if (!$this->installOrderState()) {
            return false;
        }

        if (!$this->installSQL()) {
            return false;
        }

        if (!parent::install()
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('header')
        ) {
            return false;
        }

        Configuration::updateValue('PAYBEAR_TITLE', 'Crypto Payments (BTC/ETH/LTC and others)');
        Configuration::updateValue('PAYBEAR_DESCRIPTION', 'Bitcoin (BTC), Ethereum (ETH) and other crypto currencies');
        Configuration::updateValue('PAYBEAR_EXCHANGE_LOCKTIME', '15');

        return true;
    }

    public function hookHeader()
    {
        if (Tools::getValue('controller') == "payment") {
            $this->context->controller->registerStylesheet($this->name . '-css', 'modules/' . $this->name . '/views/css/paybear.css');
            $this->context->controller->registerJavascript($this->name . '-lib-js', 'modules/' . $this->name . '/views/js/paybear.js');
            $this->context->controller->registerJavascript($this->name . '-js', 'modules/' . $this->name . '/views/js/payment.js');
        }
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        if (!Configuration::get('PAYBEAR_API_SECRET')) {
            return;
        }

        $payment_options = [
            $this->getEmbeddedPaymentOption(),
        ];

        return $payment_options;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getEmbeddedPaymentOption()
    {
        libxml_use_internal_errors(true);
        $embeddedOption = new PaymentOption();
        $embeddedOption
            ->setModuleName($this->name)
            ->setCallToActionText(Configuration::get('PAYBEAR_TITLE'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
            ->setAdditionalInformation(Configuration::get('PAYBEAR_DESCRIPTION'))
            // ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/crypto.png'))
        ;

        return $embeddedOption;
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name))
        {
            $values = Tools::getAllValues();
            foreach ($values as $name => $value) {
                if (strstr($name, 'paybear_')) {
                    Configuration::updateValue(strtoupper($name), $value);
                }
            }
            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }
        return $output.$this->displayForm();
    }


    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $fields_form[0]['form']['input'] = array(
            array(
                'type' => 'text',
                'required' => true,
                'label' => 'Title',
                'name' => 'paybear_title',
            ),
            array(
                'type' => 'textarea',
                'label' => 'Description',
                'name' => 'paybear_description'
            ),
            array(
                'type' => 'text',
                'required' => true,
                'label' => 'API Key (Secret)',
                'name' => 'paybear_api_secret'
            ),
            array(
                'type' => 'text',
                'required' => true,
                'label' => 'API Key (Public)',
                'name' => 'paybear_api_public'
            ),
            array(
                'type' => 'text',
                'label' => 'Exchange Rate Lock Time',
                'name' => 'paybear_exchange_locktime',
                'desc' => 'Lock Fiat to Crypto exchange rate for this long (in minutes, 15 is the recommended minimum)'
            ),
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                        '&token='.Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['paybear_title'] = Configuration::get('PAYBEAR_TITLE');
        $helper->fields_value['paybear_description'] = Configuration::get('PAYBEAR_DESCRIPTION');
        $helper->fields_value['paybear_exchange_locktime'] = Configuration::get('PAYBEAR_EXCHANGE_LOCKTIME');
        $helper->fields_value['paybear_api_secret'] = Configuration::get('PAYBEAR_API_SECRET');
        $helper->fields_value['paybear_api_public'] = Configuration::get('PAYBEAR_API_PUBLIC');

        return $helper->generateForm($fields_form);
    }

    private function installSQL()
    {
        $sql = array();

        $sql[] = "CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."paybear_data` (
              `id_paybear` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
              `order_reference` VARCHAR(9) NOT NULL,
              `token` VARCHAR(256) NULL DEFAULT NULL,
              `address` VARCHAR(256),
              `invoice` VARCHAR(256),
              `amount` DECIMAL(20, 8),
              `confirmations` INT(2) NULL DEFAULT NULL,
              `max_confirmations` INT(2) NULL DEFAULT NULL,
              `date_add` DATETIME NULL DEFAULT NULL,
              `date_upd` DATETIME NULL DEFAULT NULL,
              `payment_add` DATETIME NULL DEFAULT NULL,
              KEY `order_reference` (`order_reference`),
              KEY `token` (`token`)
        ) ENGINE = "._MYSQL_ENGINE_;

        foreach ($sql as $q) {
            if (!DB::getInstance()->execute($q)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create order state
     * @return boolean
     */
    public function installOrderState()
    {
        $states = array(
            array(
                'name' => 'PAYBEAR_OS_WAITING',
                'color' => '#4775de',
                'title' => 'Awaiting for PayBear payment'
            ),
            array(
                'name' => 'PAYBEAR_OS_WAITING_CONFIRMATIONS',
                'color' => '#4775de',
                'title' => 'Awaiting for PayBear payment confirmations'
            ),
            array(
                'name' => 'PAYBEAR_OS_MISPAID',
                'color' => '#8f0621',
                'title' => 'Mispaid'
            ),
            array(
                'name' => 'PAYBEAR_OS_LATE_PAYMENT_RATE_CHANGED',
                'color' => '#8f0621',
                'title' => 'Late Payment/Rate changed'
            ),
        );

        foreach ($states as $state) {
            if (!Configuration::get($state['name'])
                || !Validate::isLoadedObject(new OrderState(Configuration::get($state['name'])))) {
                $orderState = new OrderState();
                $orderState->name = array();
                foreach (Language::getLanguages() as $language) {
                    $orderState->name[$language['id_lang']] = $state['title'];
                }
                $orderState->send_email = false;
                $orderState->color = $state['color'];
                $orderState->hidden = false;
                $orderState->delivery = false;
                $orderState->logable = false;
                $orderState->invoice = false;
                if ($orderState->add()) {
                    $source = _PS_MODULE_DIR_.'paybear/logo_os.png';
                    $destination = _PS_ROOT_DIR_.'/img/os/'.(int) $orderState->id.'.gif';
                    copy($source, $destination);
                }
                Configuration::updateValue($state['name'], (int) $orderState->id);
            }
        }

        return true;
    }
}
