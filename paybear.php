<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

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

    protected $cryptoCurrencies = array(
        array('name' => 'eth', 'label' => 'ETH', 'defaults' => array('enabled' => 1, 'confirmations' => 3)),
        array('name' => 'btc', 'label' => 'BTC', 'defaults' => array('enabled' => 1, 'confirmations' => 1)),
        array('name' => 'bch', 'label' => 'BCH', 'defaults' => array('enabled' => 0, 'confirmations' => 3)),
        array('name' => 'btg', 'label' => 'BTG', 'defaults' => array('enabled' => 0, 'confirmations' => 3)),
        array('name' => 'dash', 'label' => 'DASH', 'defaults' => array('enabled' => 1, 'confirmations' => 3)),
        array('name' => 'ltc', 'label' => 'LTC', 'defaults' => array('enabled' => 1, 'confirmations' => 3)),
    );

    public function __construct()
    {
        $this->name = 'paybear';
        $this->tab = 'payments_gateways';
        $this->version = '0.1.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'PayBear';
        $this->controllers = array('validation', 'currencies', 'payment', 'callback', 'status');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Crypto Payment Gateway for PrestaShop by PayBear.io');
        $this->description = $this->l('Allows to accept crypto payments such as Bitcoin (BTC) and Ethereum (ETH)');
        // $this->module_link = $this->context->link->getAdminLink('AdminModules', true).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    public function install()
    {
        // Registration order status
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
            // || !$this->registerHook('displayPaymentByBinaries')
        ) {
            return false;
        }

        // todo: set default config
        foreach ($this->cryptoCurrencies as $cryptoCurrency) {
            if (!Configuration::updateValue('PAYBEAR_ENABLE_'.strtoupper($cryptoCurrency['name']), $cryptoCurrency['defaults']['enabled'])) {
                return false;
            }
            if (!Configuration::updateValue('PAYBEAR_' . strtoupper($cryptoCurrency['name']) . '_CONFIRMATIONS', $cryptoCurrency['defaults']['confirmations'])) {
                return false;
            }

            if (!Configuration::updateValue('PAYBEAR_' . strtoupper($cryptoCurrency['name']) . '_WALLET', '')) {
                return false;
            }
        }

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
            ->setCallToActionText($this->trans('Pay by Crypto (Embedded)'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
            ->setAdditionalInformation($this->context->smarty->fetch('module:paybear/views/templates/front/payment_infos.tpl'))
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

        $fields_form[0]['form']['input'] = array();

        foreach ($this->cryptoCurrencies as $currency) {
            $fields_form[0]['form']['input'][] = array(
                'type' => 'switch',
                'label' => $this->l('Enable ' . $currency['label']),
                'name' => 'paybear_enable_' . $currency['name'],
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->l('Yes'),
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->l('No'),
                    )
                ),
            );

            $fields_form[0]['form']['input'][] = array(
                'type' => 'text',
                'label' => $this->l($currency['label'] . ' Confirmations'),
                'name' => 'paybear_'.$currency['name'].'_confirmations',
            );
            $fields_form[0]['form']['input'][] = array(
                'type' => 'text',
                'label' => $this->l( $currency['label'] . ' Payout wallet'),
                'name' => 'paybear_'.$currency['name'].'_wallet',
            );
        }

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
        foreach ($this->cryptoCurrencies as $currency) {
            $helper->fields_value['paybear_enable_' . $currency['name']] = Configuration::get('PAYBEAR_ENABLE_' . strtoupper($currency['name']));
            $helper->fields_value['paybear_' . $currency['name'] . '_confirmations'] = Configuration::get('PAYBEAR_' . strtoupper($currency['name']) . '_CONFIRMATIONS');
            $helper->fields_value['paybear_' . $currency['name'] . '_wallet'] = Configuration::get('PAYBEAR_' . strtoupper($currency['name']) . '_WALLET');
        }

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
              `confirmations` INT(2) NULL DEFAULT NULL,
              `date_add` DATETIME NULL DEFAULT NULL,
              `date_upd` DATETIME NULL DEFAULT NULL
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
        if (!Configuration::get('PAYBEAR_OS_WAITING')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('PAYBEAR_OS_WAITING')))) {
            $orderState = new OrderState();
            $orderState->name = array();
            foreach (Language::getLanguages() as $language) {
                if (Tools::strtolower($language['iso_code']) == 'fr') {
                    $orderState->name[$language['id_lang']] = 'En attente de paiement PayBear';
                } else {
                    $orderState->name[$language['id_lang']] = 'Awaiting for PayBear payment';
                }
            }
            $orderState->send_email = false;
            $orderState->color = '#4775de';
            $orderState->hidden = false;
            $orderState->delivery = false;
            $orderState->logable = false;
            $orderState->invoice = false;
            if ($orderState->add()) {
                $source = _PS_MODULE_DIR_.'paybear/logo_os.png';
                $destination = _PS_ROOT_DIR_.'/img/os/'.(int) $orderState->id.'.gif';
                copy($source, $destination);
            }
            Configuration::updateValue('PAYBEAR_OS_WAITING', (int) $orderState->id);
        }

        if (!Configuration::get('PAYBEAR_OS_WAITING_CONFIRMATIONS')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('PAYBEAR_OS_WAITING_CONFIRMATIONS')))) {
            $orderState = new OrderState();
            $orderState->name = array();
            foreach (Language::getLanguages() as $language) {
                $orderState->name[$language['id_lang']] = 'Awaiting for PayBear payment confirmations';
            }
            $orderState->send_email = false;
            $orderState->color = '#4775de';
            $orderState->hidden = false;
            $orderState->delivery = false;
            $orderState->logable = false;
            $orderState->invoice = false;
            if ($orderState->add()) {
                $source = _PS_MODULE_DIR_.'paybear/logo_os.png';
                $destination = _PS_ROOT_DIR_.'/img/os/'.(int) $orderState->id.'.gif';
                copy($source, $destination);
            }
            Configuration::updateValue('PAYBEAR_OS_WAITING_CONFIRMATIONS', (int) $orderState->id);
        }

        return true;
    }
}
