<?php

include_once(_PS_MODULE_DIR_.'paybear/sdk/PayBearSDK.php');

class PayBearCurrenciesModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $this->ajax = true;
        $sdk = new PayBearSDK($this->context);

        $orderId = Tools::getValue('order');

        if (isset($_GET['token'])) {
            $token = $_GET['token'];
            $data = $sdk->getCurrency($token, $orderId, true);
        } else {
            $data = [];
            $currencies = $sdk->getCurrencies();
            foreach ($currencies as $token => $currency) {
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
