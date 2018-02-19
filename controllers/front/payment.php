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

        if ($order->current_state != (int) Configuration::get('PAYBEAR_OS_WAITING')) {
            Tools::redirect('index.php?controller=order');
        }

        if ((float) _PS_VERSION_ < 1.7) {
            $redirectTo = 'index.php?controller=order-detail&&id_order='.$order->id.'&key='.$customer->secure_key;
        } else {
            $redirectTo = 'index.php?controller=order-confirmation&id_cart='.$order->id_cart.'&id_module='.$this->module->id.'&id_order='.$order->reference.'&key='.$customer->secure_key;
        }

        $this->context->smarty->assign([
            'currencies' => $this->context->link->getModuleLink('paybear', 'currencies', array('order' => $orderReference)),
            'status' => $this->context->link->getModuleLink('paybear', 'status', array('order' => $orderReference)),
            'redirect' => $redirectTo,
            'fiatValue' => (float)$order->total_paid,
            'shopCurrency' => $this->context->currency,
            'minOverpaymentFiat' => Configuration::get('PAYBEAR_MIN_OVERPAYMENT'),
            'maxUnderpaymentFiat' => Configuration::get('PAYBEAR_MAX_UNDERPAYMENT')
        ]);

        if ((float) _PS_VERSION_ < 1.7) {
            $template = 'payment16.tpl';
        } else {
            $template = 'module:paybear/views/templates/front/payment.tpl';
        }

        $this->setTemplate($template);
    }
}
