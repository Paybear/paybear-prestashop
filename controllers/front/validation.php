<?php


/**
 * @property PaymentModule $module
 */
class PayBearValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        $cart = $this->context->cart;
        if (!$cart->id_customer || !$cart->id_address_delivery || !$cart->id_address_invoice || !$this->module->active) {
            $logMessage = sprintf(
                'PayBear: order validation failed. id_customer: %s, id_address_delivery: %s, id_address_invoice: %s',
                $cart->id_customer,
                $cart->id_address_delivery,
                $cart->id_address_invoice
            );
            PrestaShopLogger::addLog($logMessage, 1, null, 'Cart', $cart->id, true);
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] === 'paybear') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->getTranslator()->trans('This payment method is not available.', [], 'Modules.Wirepayment.Shop'));
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            $logMessage = sprintf('Paybear: order validation failed. Customer not found (%s)', $cart->id_customer);
            PrestaShopLogger::addLog($logMessage, 1, null, 'Cart', $cart->id, true);
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = $this->context->currency;
        $mailVars = array();

        $this->module->validateOrder($cart->id, Configuration::get('PAYBEAR_OS_WAITING'), 0, $this->module->displayName, NULL, $mailVars, (int)$currency->id, false, $customer->secure_key);
        if ((float) _PS_VERSION_ < 1.7) {
            $order = new Order(Order::getOrderByCartId($cart->id));
        } else {
            $order = new Order(Order::getIdByCartId($cart->id));
        }

        $link = $this->context->link->getModuleLink($this->module->name, 'pay', array('order' => $order->reference));
        Tools::redirect($link);
    }
}
