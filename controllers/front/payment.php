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

        if ($order->current_state != (int) Configuration::get('PAYBEAR_OS_WAITING')) {
            Tools::redirect('index.php?controller=order');
        }

        $customer = $order->getCustomer();

        $this->context->smarty->assign([
            'currencies' => $this->context->link->getModuleLink('paybear', 'currencies', array('order' => $orderReference)),
            'status' => $this->context->link->getModuleLink('paybear', 'status', array('order' => $orderReference)),
            'redirect' => 'index.php?controller=order-confirmation&id_cart='.$order->id_cart.'&id_module='.$this->module->id.'&id_order='.$order->reference.'&key='.$customer->secure_key,
            'fiatValue' => (float)$order->total_paid,
            'shopCurrency' => $this->context->currency,
        ]);

        $this->setTemplate('module:paybear/views/templates/front/payment.tpl');
    }
}
