<?php

include_once(_PS_MODULE_DIR_.'paybear/sdk/PayBearSDK.php');

class PayBearCurrenciesModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $this->ajax = true;
        $sdk = new PayBearSDK($this->context);

        // $orderId = (int) $_GET['order'];
        $orderId = Tools::getValue('order');
        $fiatTotal = 19.99; //get from order

        $tokens = ['ETH', 'BTC', 'LTC', 'BCH', 'BTG', 'DASH'];

        if (isset($_GET['token'])) {
            $token = $_GET['token'];
            $data = $sdk->getCurrency($token, $orderId, true);
        } else {
            $data = [];
            foreach ($tokens as $token) {
                $enabled = Configuration::get('PAYBEAR_ENABLE_'.strtoupper($token));
                $wallet = Configuration::get('PAYBEAR_' . strtoupper($token) . '_WALLET');
                $confirmations = Configuration::get('PAYBEAR_' . strtoupper($token) . '_CONFIRMATIONS');

                if (!$enabled || !$wallet || !$confirmations) {
                    continue;
                }

                $currency = $sdk->getCurrency($token, $orderId);
                if ($currency) {
                    $data[] = $currency;
                }
            }
        }

        echo Tools::jsonEncode($data);
        die();
    }
}
