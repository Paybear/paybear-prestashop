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

        $data = file_get_contents('php://input');

        if ($data) {
            $params = json_decode($data);
            $paybearData = PaybearData::getByOrderRefenceAndToken($orderReference, $params->blockchain);
            $maxConfirmations = $paybearData->max_confirmations;
            $invoice = $params->invoice;

            $paybearData->confirmations = $params->confirmations;
            $paybearData->update();

            PrestaShopLogger::addLog(sprintf('PayBear: incoming callback. Confirmations - %d', $params->confirmations), 1, null, 'Order', $order->id, true);

            if ($params->confirmations >= $maxConfirmations) {
                $toPay = $paybearData->amount;
                $amountPaid = $params->inTransaction->amount / pow(10, $params->inTransaction->exp);
                $fiatPaid = $amountPaid * $sdk->getRate($params->blockchain);
                $maxDifference = 0.00000001;
                $paybear = Module::getInstanceByName('paybear');

                PrestaShopLogger::addLog(sprintf('PayBear: to pay %s', $toPay), 1, null, 'Order', $order->id, true);
                PrestaShopLogger::addLog(sprintf('PayBear: paid %s', $amountPaid), 1, null, 'Order', $order->id, true);
                PrestaShopLogger::addLog(sprintf('PayBear: maxDifference %s', $maxDifference), 1, null, 'Order', $order->id, true);

                $orderStatus = Configuration::get('PS_OS_ERROR');

                if ($toPay > 0 && ($toPay - $fiatPaid) < $maxDifference) {
                    $orderTimestamp = strtotime($order->date_add);
                    $paymentTimestamp = strtotime($paybearData->payment_add);
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
            } elseif ($order->current_state != (int) Configuration::get('PAYBEAR_OS_WAITING_CONFIRMATIONS')) {
                $paybearData->payment_add = date('Y-m-d H:i:s');
                $paybearData->update();

                $orderHistory = new OrderHistory();
                $orderHistory->id_order = (int) $order->id;
                $orderHistory->changeIdOrderState((int) Configuration::get('PAYBEAR_OS_WAITING_CONFIRMATIONS'), $order, false);
                $order->add(true);
            }
        }
        die();
    }
}
