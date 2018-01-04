<?php

include_once(_PS_MODULE_DIR_.'paybear/sdk/PayBearSDK.php');

class PayBearPaymentModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $orderReference = Tools::getValue('order');

        /** @var Order $order */
        $order = Order::getByReference($orderReference)->getFirst();
        $customer = $order->getCustomer();

        $this->context->smarty->assign([
            'currencies' => $this->context->link->getModuleLink('paybear', 'currencies', array('order' => $orderReference)),
            'status' => $this->context->link->getModuleLink('paybear', 'status', array('order' => $orderReference)),
            'redirect' => 'index.php?controller=order-confirmation&id_cart='.$order->id_cart.'&id_module='.$this->module->id.'&id_order='.$order->reference.'&key='.$customer->secure_key,
            'fiatValue' => (float)$order->total_paid,
            'shopCurrency' => $this->context->currency,
        ]);

        $this->setTemplate('module:paybear/views/templates/front/payment.tpl');


        // $this->ajax = true;
        // $sdk = new PayBearSDK($this->context);
        //
        // // $orderId = (int) $_GET['order'];
        // $orderId = 123;
        // $fiatTotal = 19.99; //get from order
        //
        // $tokens = ['ETH', 'BTC', 'LTC', 'BCH', 'BTG', 'DASH'];
        //
        //
        // if (isset($_GET['token'])) {
        //     $token = $_GET['token'];
        //     $data = $sdk->getCurrency($token, $orderId, true);
        // } else {
        //     $data = [];
        //     foreach ($tokens as $token) {
        //         $enabled = Configuration::get('paybear_enable_'.strtolower($token));
        //         $wallet = Configuration::get('paybear_' . strtolower($token) . '_wallet');
        //         $confirmations = Configuration::get('paybear_' . strtolower($token) . '_confirmations');
        //
        //         if (!$enabled || !$wallet || !$confirmations) {
        //             continue;
        //         }
        //
        //         $currency = $sdk->getCurrency($token, $orderId);
        //         if ($currency) {
        //             $data[] = $currency;
        //         }
        //     }
        // }
        //
        // echo Tools::jsonEncode($data);
        // die();
    }
}
