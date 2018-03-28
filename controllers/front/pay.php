<?php

include_once(_PS_MODULE_DIR_.'paybear/sdk/PayBearSDK.php');

class PayBearPayModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $orderReference = Tools::getValue('order');
        /** @var Order $order */
        $order = Order::getByReference($orderReference)->getFirst();
        $customer = $order->getCustomer();
        $currency = Currency::getCurrencyInstance((int)$order->id_currency);
        $sdk = new PayBearSDK($this->context);
        $paybearData = PaybearData::getByOrderRefence($orderReference);
        $allPaybearPayments = [];
        $blockExplorer = null;
        $paymentStatus = 'pending payment';

        $statusPaymentAccepted = Configuration::get('PS_OS_PAYMENT');
        $statusWaitingForConfirmations = Configuration::get('PAYBEAR_OS_WAITING_CONFIRMATIONS');
        $statusMispaid = Configuration::get('PAYBEAR_OS_MISPAID');

        // var_dump($order->current_state);
        // var_dump(Configuration::get('PS_OS_PAYMENT'));
        // die();

        if ($order->current_state == Configuration::get('PS_OS_PAYMENT')) {
            $paymentStatus = 'paid';
        }

        if ($order->current_state == Configuration::get('PAYBEAR_OS_WAITING_CONFIRMATIONS')) {
            $paymentStatus = 'waiting for confirmations';
        }

        if ($order->current_state == Configuration::get('PAYBEAR_OS_MISPAID')) {
            $paymentStatus = 'partial payment';
        }

        if ($paybearData) {
            $allPaybearPayments = $paybearData->getPayments();
            $selectedCurrency = $sdk->getCurrency($paybearData->token, $orderReference);
            $blockExplorer = sprintf($selectedCurrency->blockExplorer, $paybearData->address);
        }

        $maxUnderpaymentFiat = Configuration::get('PAYBEAR_MAX_UNDERPAYMENT');
        $toPayFiat = $order->total_paid;
        $alreadyPaid = 0;
        $alreadyPaidFiat = 0;
        foreach ($allPaybearPayments as $payment) {
            $alreadyPaid += $payment->amount;
        }

        if ($alreadyPaid > 0) {
            $rate = round($order->total_paid / $paybearData->amount, 8);
            $alreadyPaidFiat = $alreadyPaid * $rate;
            $toPayFiat = $toPayFiat - $alreadyPaidFiat;
        }

        // if (!in_array($order->current_state, [
        //         (int) Configuration::get('PAYBEAR_OS_WAITING'),
        //         (int) Configuration::get('PAYBEAR_OS_MISPAID'),
        //     ])) {
        //     $logMessage = sprintf('Paybear: payment failed. order: %s, order status: %s', $order->id, $order->current_state);
        //     PrestaShopLogger::addLog($logMessage, 1, null, 'Order', $order->id, true);
        //     Tools::redirect('index.php?controller=order');
        // }

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
            'maxUnderpaymentFiat' => Configuration::get('PAYBEAR_MAX_UNDERPAYMENT'),
            'order' => $order,
            'total' => Tools::displayPrice($order->total_paid, $currency),
            'paybearData' => $paybearData,
            'blockExplorer' => $blockExplorer,
            'alreadyPaid' => $alreadyPaid,
            'alreadyPaidFiat' => $alreadyPaidFiat,
            'alreadyPaidFiatFormatted' => Tools::displayPrice($alreadyPaidFiat, $currency),
            'toPayFiat' => $toPayFiat,
            'toPayFiatFormatted' => Tools::displayPrice($toPayFiat, $currency),
            'paymentStatus' => $paymentStatus,
            'statusPaymentAccepted' => $statusPaymentAccepted,
            'statusWaitingForConfirmations' => $statusWaitingForConfirmations,
            'statusMispaid' => $statusMispaid
        ]);

        if ((float) _PS_VERSION_ < 1.7) {
            $template = 'payment16.tpl';
        } else {
            $template = 'module:paybear/views/templates/front/payment.tpl';
        }

        $this->setTemplate($template);
    }
}
