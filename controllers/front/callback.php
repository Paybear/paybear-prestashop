<?php

include_once(_PS_MODULE_DIR_.'paybear/sdk/PayBearSDK.php');

class PayBearCallbackModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $orderReference = $_GET['order'];
        /** @var Order $order */
        $order = Order::getByReference($orderReference)->getFirst();
        $cart = new Cart($order->id_cart);
        $currency = new Currency($cart->id_currency);
        $customer = $order->getCustomer();

        $data = file_get_contents('php://input');
        if ($data) {
            $params = json_decode($data);
            $minConfirmations = Configuration::get('PAYBEAR_' . strtoupper($params->blockchain) . '_CONFIRMATIONS');
            $invoice = $params->invoice;
            $paybearData = PaybearData::getByOrderRefenceAndToken($orderReference, $params->blockchain);
            $paybearData->confirmations = $params->confirmations;
            $paybearData->update();

            if ($params->confirmations >= $minConfirmations) {
                $amountPaid = $params->inTransaction->amount / pow(10, $params->inTransaction->exp);
                $paybear = Module::getInstanceByName('paybear');

                //compare $amountPaid with order total
                //compare $invoice with one saved in the database to ensure callback is legitimate
                //mark the order as paid
                $paybear->validateOrder($cart->id, Configuration::get('PS_OS_PAYMENT'), $amountPaid, $this->module->displayName, NULL, array(), (int)$currency->id, false, $customer->secure_key);

                echo $invoice; //stop further callbacks
                die();
            }
        }
        die();
        // echo Tools::jsonEncode($data);
        // die();
    }
}
