<?php

include_once _PS_MODULE_DIR_ . 'paybear/sdk/PayBearSDK.php';

class PayBearCallbackModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $orderReference = $_GET['order'];

        if (!$orderReference) {
            die();
        }

        /** @var Order $order */
        $order = Order::getByReference($orderReference)->getFirst();

        $currency = new Currency($order->id_currency);
        $customer = $order->getCustomer();
        $sdk = new PayBearSDK($this->context);
        $currencies = $sdk->getCurrencies();
        $data = file_get_contents('php://input');
        $message = null;
        $addMessage = true;
        $paybear = Module::getInstanceByName('paybear');
        $updateStatus = true;

        if ((int) $order->current_state === (int) Configuration::get('PS_OS_PAYMENT')) {
            die();
        }

        if (!$data) {
            die();
        }

        PrestaShopLogger::addLog(sprintf('PayBear: incoming callback (%s)', $data), 1, null, 'Order', $order->id, true);

        $params = json_decode($data);
        $paybearData = PaybearData::getByOrderRefence($orderReference);

        if (!$paybearData) {
            die();
        }

        if ($paybearData->invoice !== $params->invoice) {
            PrestaShopLogger::addLog(sprintf('PayBear: Wrong invoice - %s. expected - %s', $paybearData->invoice, $params->invoice), 1, null, 'Order', $order->id, true);
            die();
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        $allPaybearPayments = $paybearData->getPayments($params->inTransaction->hash);
        $maxConfirmations = $params->maxConfirmations;
        $blockchain = PayBearSDK::sanitizeToken($params->blockchain);
        $blockchainCode = strtoupper($blockchain);

        if (isset($currencies[$params->blockchain])) {
            $blockchainCode = $currencies[$params->blockchain]['code'];
        }

        $rate = $sdk->getRate($blockchain);
        if (!$maxConfirmations) {
            $maxConfirmations = $paybearData->max_confirmations; // todo: tmp fix
        }
        $maxUnderpaymentFiat = Configuration::get('PAYBEAR_MAX_UNDERPAYMENT');
        $maxUnderpaymentCrypto = $maxUnderpaymentFiat / $rate;
        $maxDifference = max($maxUnderpaymentCrypto, 0.00000001);
        $response = null;

        $toPay = $paybearData->amount;
        $alreadyPaid = 0;

        foreach ($allPaybearPayments as $payment) {
            $alreadyPaid += $payment->amount;
        }

        $paidNow = $params->inTransaction->amount / (10 ** $params->inTransaction->exp);
        $totalPaid = $paidNow + $alreadyPaid;

        /** @noinspection PhpUnhandledExceptionInspection */
        $paybearPayment = PaybearTransaction::getByTransactionHash($params->inTransaction->hash);
        if (!$paybearPayment) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $paybearPayment = new PaybearTransaction();
            $paybearPayment->invoice = $params->invoice;
            $paybearPayment->max_confirmations = $params->maxConfirmations;
            $paybearPayment->order_reference = $orderReference;
            $paybearPayment->blockchain = $blockchain;
            $paybearPayment->amount = sprintf('%.8F', $paidNow);
            $paybearPayment->currency = $currency->iso_code;
            $paybearPayment->address = $paybearData->address;
            $paybearPayment->transaction_hash = $params->inTransaction->hash;
        } else {
            $addMessage = false; // message already sent
        }

        if (isset($allPaybearPayments[$paybearPayment->transaction_hash])) {
            $transactionIndex = array_search($paybearPayment->transaction_hash, array_keys($allPaybearPayments), true);
            if ($transactionIndex > 0) { //avoid race conditions
                usleep($transactionIndex * 500);
            }
        }

        $paybearPayment->confirmations = $params->confirmations;

        if (!$paybearPayment->id_paybear_transaction) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $paybearPayment->save();
        } else {
            /** @noinspection PhpUnhandledExceptionInspection */
            $paybearPayment->update();
        }

        if (($toPay - $totalPaid) > $maxDifference) {
            if ((int) $order->current_state !== (int) Configuration::get('PAYBEAR_OS_MISPAID')) {
                $order->setCurrentState((int) Configuration::get('PAYBEAR_OS_MISPAID'));
            }
            $updateStatus = false;
            $underpaid = $toPay - $totalPaid;
            $underpaidFiat = $underpaid * $rate;
            // $underpaidFiat = round(($toPay-$totalPaid) * $rate, 2);
            $message = sprintf("Looks like you underpaid %.8F %s (%.2F %s)\n\nDon't worry, here is what to do next:\n\nContact the merchant directly and...\n-Request details on how you can pay the difference..\n-Request a refund and create a new order.\n\nTips for Paying with Crypto:\n\nTip 1) When paying, ensure you send the correct amount in %s. Do not manually enter the %s Value.\n\nTip 2)  If you are sending from an exchange, be sure to correctly factor in their withdrawal fees.\n\nTip 3) Be sure to successfully send your payment before the countdown timer expires.\nThis timer is setup to lock in a fixed rate for your payment. Once it expires, the rate changes.", $underpaid, strtoupper($blockchainCode), $underpaidFiat, $currency->iso_code, strtoupper($blockchainCode), $currency->iso_code);
        }

        if ($params->confirmations >= $maxConfirmations && $maxConfirmations > 0) {
            $orderStatus = Configuration::get('PAYBEAR_OS_MISPAID');

            /** @noinspection NotOptimalIfConditionsInspection */
            if ($toPay > 0 && ($toPay - $totalPaid) < $maxDifference) {
                $orderTimestamp = strtotime($order->date_add);
                $paymentTimestamp = strtotime($paybearPayment->date_add);
                $deadline = $orderTimestamp + Configuration::get('PAYBEAR_EXCHANGE_LOCKTIME') * 60;
                $orderStatus = Configuration::get('PS_OS_PAYMENT');

                if ($paymentTimestamp > $deadline) {
                    $orderStatus = Configuration::get('PAYBEAR_OS_LATE_PAYMENT_RATE_CHANGED');

                    $fiatPaid = $totalPaid * $rate;
                    if ($order->total_paid > $fiatPaid) {
                        $underpaid = $toPay - $totalPaid;
                        $underpaidFiat = $underpaid * $rate;
                        PrestaShopLogger::addLog('PayBear: rate changed', 1, null, 'Order', $order->id, true);
                        $message = sprintf("Looks like you underpaid %.8F %s (%.2F %s)\nThis was due to the payment being sent after the Countdown Timer Expired.\n\nDon't worry, here is what to do next:\n\nContact the merchant directly and...\n-Request details on how you can pay the difference..\n-Request a refund and create a new order.\n\nTips for Paying with Crypto:\n\nTip 1) When paying, ensure you send the correct amount in %s. Do not manually enter the %s Value.\n\nTip 2)  If you are sending from an exchange, be sure to correctly factor in their withdrawal fees.\n\nTip 3) Be sure to successfully send your payment before the countdown timer expires.\nThis timer is setup to lock in a fixed rate for your payment. Once it expires, the rate changes.", $underpaid, strtoupper($blockchainCode), $underpaidFiat, $currency->iso_code, strtoupper($blockchainCode), $currency->iso_code);
                        $addMessage = true;
                        // $message = sprintf('Late Payment / Rate changed (%s %s paid, %s %s expected)', $fiatPaid, $currency->iso_code, $order->total_paid, $currency->iso_code);
                    } else {
                        $orderStatus = Configuration::get('PS_OS_PAYMENT');
                        $order->addOrderPayment($fiatPaid, $paybear->displayName, $params->inTransaction->hash);
                        PrestaShopLogger::addLog('PayBear: payment complete', 1, null, 'Order', $order->id, true);
                    }
                }

                $overpaid = $totalPaid - $toPay;
                $overpaidFiat = round(($totalPaid - $toPay) * $rate, 2);
                $minOverpaymentFiat = Configuration::get('PAYBEAR_MIN_OVERPAYMENT');

                if ($overpaidFiat > $minOverpaymentFiat) {
                    $message = sprintf("Whoops, you overpaid: %.8F %s\n\nDon’t worry, here is what to do next:\nTo get your overpayment refunded, please contact the merchant directly and share your Order ID %s and %s Address to send your refund to.\n\nTips for Paying with Crypto:\n\nTip 1) When paying, ensure you send the correct amount in %s. Do not manually enter the %s Value.\n\nTip 2)  If you are sending from an exchange, be sure to correctly factor in their withdrawal fees.\n\nTip 3) Be sure to successfully send your payment before the countdown timer expires.\nThis timer is setup to lock in a fixed rate for your payment. Once it expires, the rate changes.", $overpaid, strtoupper($blockchainCode), $orderReference, strtoupper($blockchainCode), strtoupper($blockchainCode), strtoupper($currency->iso_code));
                    $addMessage = true;
                }
            }

            if ($updateStatus && (int) $order->current_state !== (int) $orderStatus) {
                $order->setCurrentState($orderStatus);
            }
            $response = $params->invoice;
        } elseif (!in_array((int) $order->current_state, [
            (int) Configuration::get('PAYBEAR_OS_WAITING_CONFIRMATIONS'),
            (int) Configuration::get('PAYBEAR_OS_MISPAID'),
        ], true)) {
            $paybearData->payment_add = date('Y-m-d H:i:s');
            /** @noinspection PhpUnhandledExceptionInspection */
            $paybearData->update();

            $order->setCurrentState((int) Configuration::get('PAYBEAR_OS_WAITING_CONFIRMATIONS'));
        } elseif ($toPay > 0 && ($toPay - $totalPaid) < $maxDifference) {
            $order->setCurrentState((int) Configuration::get('PAYBEAR_OS_WAITING_CONFIRMATIONS'));
        }

        // Send message to customer if needed
        if ($message && $addMessage) {
            $idCustomerThread = CustomerThread::getIdCustomerThreadByEmailAndIdOrder($customer->email, $order->id);
            if (!$idCustomerThread) {
                /** @noinspection PhpUnhandledExceptionInspection */
                $customerThread = new CustomerThread();
                $customerThread->id_contact = 0;
                $customerThread->id_customer = (int)$order->id_customer;
                $customerThread->id_shop = (int)$this->context->shop->id;
                $customerThread->id_order = (int)$order->id;
                $customerThread->id_lang = (int)$this->context->language->id;
                $customerThread->email = $customer->email;
                $customerThread->status = 'open';
                $customerThread->token = Tools::passwdGen(12);
                /** @noinspection PhpUnhandledExceptionInspection */
                $customerThread->add();
            } else {
                /** @noinspection PhpUnhandledExceptionInspection */
                $customerThread = new CustomerThread((int) $idCustomerThread);
            }

            /** @noinspection PhpUnhandledExceptionInspection */
            $customerMessage = new CustomerMessage();
            $customerMessage->id_customer_thread = $customerThread->id;
            $customerMessage->message = $message;
            $customerMessage->private = 0;

            /** @noinspection PhpUnhandledExceptionInspection */
            $customerMessage->add();

            $message = $customerMessage->message;
            if ((int) Configuration::get('PS_MAIL_TYPE', null, null, $order->id_shop) !== Mail::TYPE_TEXT) {
                $message = Tools::nl2br($customerMessage->message);
            }

            $varsTpl = array(
                '{lastname}' => $customer->lastname,
                '{firstname}' => $customer->firstname,
                '{id_order}' => $order->id,
                '{order_name}' => $order->getUniqReference(),
                '{message}' => $message
            );

            Mail::Send(
                (int)$order->id_lang,
                'order_merchant_comment',
                'New message regarding your order',
                $varsTpl, $customer->email,
                $customer->firstname.' '.$customer->lastname,
                null,
                null,
                null,
                null,
                _PS_MAIL_DIR_,
                true,
                (int)$order->id_shop
            );

        }

        echo $response;
        die();
    }
}
