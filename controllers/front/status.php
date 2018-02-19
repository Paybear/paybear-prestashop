<?php

include_once(_PS_MODULE_DIR_.'paybear/sdk/PayBearSDK.php');

class PayBearStatusModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $orderReference = Tools::getValue('order');
        $paybearData = PaybearData::getByOrderRefence($orderReference);
        $allPayments = $paybearData->getPayments();
        $toPay = $paybearData->amount;
        $success = false;
        $unpaidConfirmations = array();
        $sdk = new PayBearSDK($this->context);
        $rate = $sdk->getRate($paybearData->token);

        $maxUnderpaymentFiat = Configuration::get('PAYBEAR_MAX_UNDERPAYMENT');
        $maxUnderpaymentCrypto = $maxUnderpaymentFiat / $rate;
        $maxDifference = max($maxUnderpaymentCrypto, 0.00000001);

        $data = array();
        $coinsPaid = 0;
        foreach ($allPayments as $payment) {
            $coinsPaid += $payment->amount;
            $confirmations = $payment->confirmations;
            $maxConfirmations = $payment->max_confirmations;
            if (!$maxConfirmations) {
                $maxConfirmations = $paybearData->max_confirmations;
            }
            if ($confirmations >= $maxConfirmations) {
                $success = true;
            }
            $unpaidConfirmations[] = $confirmations;
        }
        $data['coinsPaid'] = $coinsPaid;
        $data['success'] = $success && ($toPay > 0 && ($toPay - $coinsPaid) < $maxDifference);
        $data['confirmations'] = null;
        if (!empty($unpaidConfirmations)) {
            $data['confirmations'] = min($unpaidConfirmations);
        }

        echo Tools::jsonEncode($data); //return this data to PayBear form
        die();
    }
}
