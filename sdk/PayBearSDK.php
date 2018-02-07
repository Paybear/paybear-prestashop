<?php


class PayBearSDK
{
    public static $currencies = null;

    protected $context;

    public function __construct($context)
    {
        $this->context = $context;
    }

    public function getAddress($orderId, $token = 'ETH')
    {
        $data = PaybearData::getByOrderRefence($orderId);
        /** @var Order $order */
        $order = Order::getByReference($orderId)->getFirst();

        $apiSecret = Configuration::get('PAYBEAR_API_SECRET');
        $currencies = $this->getCurrencies();

        $rate = $this->getRate($token);

        if ($data && strtolower($data->token) == strtolower($token)) {
            return $data->address;
        } elseif (!$data) {
            $data = new PaybearData();
            $data->order_reference = $orderId;
        }

        $callbackUrl = $this->context->link->getModuleLink('paybear', 'callback', array('order' => $orderId));

        $url = sprintf('https://api.paybear.io/v2/%s/payment/%s?token=%s', strtolower($token), urlencode($callbackUrl), $apiSecret);
        if ($response = file_get_contents($url)) {
            $response = json_decode($response);

            if (isset($response->data->address)) {
                $fiatAmount = $order->total_paid;
                $coinsAmount = round($fiatAmount / $rate, 8);

                $data->confirmations = null;
                $data->token = strtolower($token);
                $data->address = $response->data->address;
                $data->invoice = $response->data->invoice;
                $data->amount = $coinsAmount;
                $data->max_confirmations = $currencies[strtolower($token)]['maxConfirmations'];

                if ($data->id_paybear) {
                    $data->update();
                } else {
                    $data->add();
                }

                return $response->data->address;
            }
        }

        return null;
    }

    public function getPayout($token)
    {
        return Configuration::get('PAYBEAR_'.strtoupper($token) . '_WALLET');
    }

    public function getCurrency($token, $orderId, $getAddress = false)
    {
        $rate = $this->getRate($token);

        if ($rate) {
            $orderReference = Tools::getValue('order');
            /** @var Order $order */
            $order = Order::getByReference($orderReference)->getFirst();
            $fiatValue = (float)$order->total_paid;
            $coinsValue = round($fiatValue / $rate, 8);

            $currencies = $this->getCurrencies();
            $currency = (object) $currencies[strtolower($token)];
            $currency->coinsValue = $coinsValue;
            $currency->rate = round($currency->rate, 2);


            if ($getAddress) {
                $currency->address = $this->getAddress($orderId, $token);
            } else {
                $currency->currencyUrl = $this->context->link->getModuleLink('paybear', 'currencies', array('token' => $token, 'order' => $orderReference));
            }

            return $currency;

        }

        echo 'can\'t get rate for ' . $token;

        return null;
    }

    public function getCurrencies()
    {
        if (self::$currencies === null) {
            $url = sprintf('https://api.paybear.io/v2/currencies?token=%s', Configuration::get('PAYBEAR_API_SECRET'));
            $response = file_get_contents($url);
            $data = json_decode($response, true);

            self::$currencies = $data['data'];
        }

        return self::$currencies;
    }


    public function getRate($curCode)
    {
        $rates = $this->getRates();
        $curCode = strtolower($curCode);

        return isset($rates->$curCode) ? $rates->$curCode->mid : false;
    }

    public function getRates()
    {
        static $rates = null;

        if (empty($rates)) {
            $currency = $this->context->currency;
            $url = sprintf("https://api.paybear.io/v2/exchange/%s/rate", strtolower($currency->iso_code));

            if ($response = file_get_contents($url)) {
                $response = json_decode($response);
                if ($response->success) {
                    $rates = $response->data;
                }
            }
        }

        return $rates;
    }
}
