<?php

include_once(_PS_MODULE_DIR_ . 'paybear/sdk/PayBearSDK.php');

class PayBearCallbackModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $orderReference = $_GET['order'];
        /** @var Order $order */
        $order = Order::getByReference($orderReference)->getFirst();
        $sdk = new PayBearSDK($this->context);

        // $currency = new Currency($order->id_currency);
        // $customer = $order->getCustomer();

        $data = file_get_contents('php://input');

        if ($data) {
            $params = json_decode($data);
            $minConfirmations = Configuration::get('PAYBEAR_' . strtoupper($params->blockchain) . '_CONFIRMATIONS');
            $invoice = $params->invoice;
            $paybearData = PaybearData::getByOrderRefenceAndToken($orderReference, $params->blockchain);
            $paybearData->confirmations = $params->confirmations;
            $paybearData->update();

            PrestaShopLogger::addLog(sprintf('PayBear: incoming callback. Confirmations - %d', $params->confirmations), 1, null, 'Order', $order->id, true);

            if ($params->confirmations >= $minConfirmations) {
                $toPay = $paybearData->amount;
                $amountPaid = $params->inTransaction->amount / pow(10, $params->inTransaction->exp);
                $fiatPaid = $amountPaid * $sdk->getRate($params->blockchain);
                $maxDifference = min($toPay * 0.005, 0.001);
                $paybear = Module::getInstanceByName('paybear');

                PrestaShopLogger::addLog(sprintf('PayBear: to pay %s', $toPay), 1, null, 'Order', $order->id, true);
                PrestaShopLogger::addLog(sprintf('PayBear: paid %s', $amountPaid), 1, null, 'Order', $order->id, true);
                PrestaShopLogger::addLog(sprintf('PayBear: maxDifference %s', $maxDifference), 1, null, 'Order', $order->id, true);

                $orderStatus = Configuration::get('PS_OS_ERROR');

                if ($toPay > 0 && ($toPay - $fiatPaid) < $maxDifference) {
                    $orderTimestamp = strtotime($order->date_add);
                    $paymentTimestamp = time();
                    $deadline = $orderTimestamp + Configuration::get('PAYBEAR_EXCHANGE_LOCKTIME') * 60;
                    $orderStatus = Configuration::get('PS_OS_PAYMENT');

                    if ($paymentTimestamp > $deadline) {
                        $orderStatus = Configuration::get('PS_OS_ERROR');
                        PrestaShopLogger::addLog('PayBear: late payment', 1, null, 'Order', $order->id, true);

                        $fiatPaid = $amountPaid * $sdk->getRate($params->blockchain);
                        if ($order->total_paid < $fiatPaid) {
                            PrestaShopLogger::addLog('PayBear: rate changed', 1, null, 'Order', $order->id, true);
                        } else {
                            $orderStatus = Configuration::get('PS_OS_PAYMENT');
                            $order->addOrderPayment($amountPaid, $paybear->displayName, $params->inTransaction->hash);
                            PrestaShopLogger::addLog(sprintf('PayBear: payment complete', $amountPaid), 1, null, 'Order', $order->id, true);
                        }
                    }
                } else {
                    PrestaShopLogger::addLog(sprintf('PayBear: wrong amount %s', $amountPaid), 2, null, 'Order', $order->id, true);
                }

                $orderHistory = new OrderHistory();
                $orderHistory->id_order = (int) $order->id;
                $orderHistory->changeIdOrderState((int) $orderStatus, $order, true);
                $orderHistory->addWithemail(true);

                echo $invoice; //stop further callbacks
                die();
            }
        }
        die();
    }
}
