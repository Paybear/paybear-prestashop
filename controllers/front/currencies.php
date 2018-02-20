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

            $paybearData = PaybearData::getByOrderRefence($orderId);
            $currentCurrencyToken = null;
            if ($paybearData) {
                $allPaybearPayments = $paybearData->getPayments();
                if (!empty($allPaybearPayments)) {
                    $firstPayment = current($allPaybearPayments);
                    $currentCurrencyToken = $firstPayment->blockchain;
                }
            }

            // tmp solution
            if ($currentCurrencyToken) {
                $currency = $sdk->getCurrency($currentCurrencyToken, $orderId, true);
                $currencies = array();
                $currencies[$currentCurrencyToken] = $currency;
            }

            foreach ($currencies as $token => $currency) {
                $currency = $sdk->getCurrency($token, $orderId, $getAddress);
                if ($currency) {
                    $coinsPaid = 0;
                    if ($paybearData && !empty($allPaybearPayments)) {
                        foreach ($allPaybearPayments as $payment) {
                            if ($payment->blockchain == strtolower($currency->code)) {
                                $coinsPaid += $payment->amount;
                            }
                        }
                    }
                    $currency->coinsPaid = $coinsPaid;
                    $data[] = $currency;
                }
            }
        }

        echo Tools::jsonEncode($data);
        die();
    }
}
