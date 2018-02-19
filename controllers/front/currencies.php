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
            $getAddress = false;
            if (count($currencies) == 1) {
                $getAddress = true;
            }
            foreach ($currencies as $token => $currency) {
                $currency = $sdk->getCurrency($token, $orderId, $getAddress);
                if ($currency) {
                    $data[] = $currency;
                }
            }
        }

        echo Tools::jsonEncode($data);
        die();
    }
}
