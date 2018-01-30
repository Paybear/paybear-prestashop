<?php

include_once(_PS_MODULE_DIR_.'paybear/sdk/PayBearSDK.php');

class PayBearStatusModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $orderReference = Tools::getValue('order');
        $paybearData = PaybearData::getByOrderRefence($orderReference);

        // $minConfirmations = Configuration::get('PAYBEAR_' . strtoupper($paybearData->token) . '_CONFIRMATIONS');;
        $maxConfirmations = $paybearData->max_confirmations;
        $confirmations = $paybearData->confirmations;
        $data = array();
        if ($confirmations >= $maxConfirmations) { //set max confirmations
            $data['success'] = true;
        } else {
            $data['success'] = false;
        }

        if (is_numeric($confirmations)) {
            $data['confirmations'] = $confirmations;
        }

        echo Tools::jsonEncode($data); //return this data to PayBear form
        die();
    }
}
