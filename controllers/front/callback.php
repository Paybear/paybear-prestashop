<?php

include_once(_PS_MODULE_DIR_ . 'paybear/sdk/PayBearSDK.php');

class PayBearCallbackModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $orderReference = $_GET['order'];
        /** @var Order $order */
        $order = Order::getByReference($orderReference)->getFirst();
        $currency = new Currency($order->id_currency);
        $customer = $order->getCustomer();
        $sdk = new PayBearSDK($this->context);

        $data = file_get_contents('php://input');

        if (in_array($order->current_state, array(
            Configuration::get('PAYBEAR_OS_MISPAID'),
            Configuration::get('PAYBEAR_OS_LATE_PAYMENT_RATE_CHANGED'),
            Configuration::get('PS_OS_PAYMENT')
        ))) {
            die();
        }

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
                $maxDifference = 0.00000001;
                $paybear = Module::getInstanceByName('paybear');

                PrestaShopLogger::addLog(sprintf('PayBear: to pay %s', $toPay), 1, null, 'Order', $order->id, true);
                PrestaShopLogger::addLog(sprintf('PayBear: paid %s', $amountPaid), 1, null, 'Order', $order->id, true);
                PrestaShopLogger::addLog(sprintf('PayBear: maxDifference %s', $maxDifference), 1, null, 'Order', $order->id, true);

                $orderStatus = Configuration::get('PAYBEAR_OS_MISPAID');
                $message = false;

                if ($toPay > 0 && ($toPay - $amountPaid) < $maxDifference) {
                    $orderTimestamp = strtotime($order->date_add);
                    $paymentTimestamp = strtotime($paybearData->payment_add);
                    $deadline = $orderTimestamp + Configuration::get('PAYBEAR_EXCHANGE_LOCKTIME') * 60;
                    $orderStatus = Configuration::get('PS_OS_PAYMENT');

                    if ($paymentTimestamp > $deadline) {
                        $orderStatus = Configuration::get('PAYBEAR_OS_LATE_PAYMENT_RATE_CHANGED');
                        PrestaShopLogger::addLog('PayBear: late payment', 1, null, 'Order', $order->id, true);

                        $fiatPaid = $amountPaid * $sdk->getRate($params->blockchain);
                        if ($order->total_paid < $fiatPaid) {
                            PrestaShopLogger::addLog('PayBear: rate changed', 1, null, 'Order', $order->id, true);
                            $message = sprintf('Late Payment / Rate changed (%s %s paid, %s %s expected)', $fiatPaid, $currency->iso_code, $order->total_paid, $currency->iso_code);
                        } else {
                            $orderStatus = Configuration::get('PS_OS_PAYMENT');
                            $order->addOrderPayment($amountPaid, $paybear->displayName, $params->inTransaction->hash);
                            PrestaShopLogger::addLog(sprintf('PayBear: payment complete', $amountPaid), 1, null, 'Order', $order->id, true);
                        }
                    }
                } else {
                    PrestaShopLogger::addLog(sprintf('PayBear: wrong amount %s', $amountPaid), 2, null, 'Order', $order->id, true);
                    $underpaid = round(($toPay-$amountPaid)*$sdk->getRate($params->blockchain), 2);
                    $message = sprintf('Wrong Amount Paid (%s %s received, %s %s expected) - %s %s underpaid', $amountPaid, $params->blockchain, $toPay, $params->blockchain, $currency->sign, $underpaid);
                }

                $order->setCurrentState($orderStatus);

                if ($message) {
                    $idCustomerThread = CustomerThread::getIdCustomerThreadByEmailAndIdOrder($customer->email, $order->id);
                    if (!$idCustomerThread) {
                        $customerThread = new CustomerThread();
                        $customerThread->id_contact = 0;
                        $customerThread->id_customer = (int)$order->id_customer;
                        $customerThread->id_shop = (int)$this->context->shop->id;
                        $customerThread->id_order = (int)$order->id;
                        $customerThread->id_lang = (int)$this->context->language->id;
                        $customerThread->email = $customer->email;
                        $customerThread->status = 'open';
                        $customerThread->token = Tools::passwdGen(12);
                        $customerThread->add();
                    } else {
                        $customerThread = new CustomerThread((int)$idCustomerThread);
                    }

                    $customerMessage = new CustomerMessage();
                    $customerMessage->id_customer_thread = $customerThread->id;
                    // $customerMessage->id_employee = (int)$this->context->employee->id;
                    $customerMessage->message = $message;
                    $customerMessage->private = 0;
                    $customerMessage->add();
                }

                echo $invoice; //stop further callbacks
                die();
            } elseif ($order->current_state != (int) Configuration::get('PAYBEAR_OS_WAITING_CONFIRMATIONS')) {
                $paybearData->payment_add = date('Y-m-d H:i:s');
                $paybearData->update();

                $order->setCurrentState((int) Configuration::get('PAYBEAR_OS_WAITING_CONFIRMATIONS'));
            }
        }
        die();
    }
}
