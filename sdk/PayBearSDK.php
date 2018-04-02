<?php


class PayBearSDK
{
    public static $rates = null;
    public static $currencies = null;

    protected $baseUrl = 'https://api.paybear.io/v2';

    protected $context;

    public function __construct($context)
    {
        $this->context = $context;
    }

    public function getAddress($orderId, $token = 'ETH')
    {
        $token = self::sanitizeToken($token);
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

        $url = sprintf('%s/%s/payment/%s?token=%s', $this->baseUrl, strtolower($token), urlencode($callbackUrl), $apiSecret);
        // var_dump($url);
        if ($response = Tools::file_get_contents($url)) {
            $response = json_decode($response);

            if (isset($response->data->address)) {
                $fiatAmount = $order->total_paid;
                $coinsAmount = round($fiatAmount / $rate, 8);

                $data->confirmations = null;
                $data->token = strtolower($token);
                $data->address = $response->data->address;
                $data->invoice = $response->data->invoice;
                $data->amount = sprintf('%.8F', $coinsAmount);
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
        $token = self::sanitizeToken($token);
        return Configuration::get('PAYBEAR_'.strtoupper($token) . '_WALLET');
    }

    public function getCurrency($token, $orderId, $getAddress = false)
    {
        $token = self::sanitizeToken($token);
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
            $currency->rate = round($this->getRate($currency->code), 2);


            if ($getAddress) {
                $currency->address = $this->getAddress($orderId, $token);
            } else {
                $currency->currencyUrl = $this->context->link->getModuleLink('paybear', 'currencies', array('token' => $token, 'order' => $orderReference));
            }

            return $currency;

        }

        return null;
    }

    public function getCurrencies()
    {
        if (self::$currencies === null) {
            $url = sprintf('%s/currencies?token=%s', $this->baseUrl, Configuration::get('PAYBEAR_API_SECRET'));
            $response = Tools::file_get_contents($url);
            $data = json_decode($response, true);
            self::$currencies = [];
            if ($data['success']) {
                self::$currencies = $data['data'];
            }
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
        if (empty(self::$rates)) {
            $needUpdate = false;
            $currency = $this->context->currency;
            $ratesKey = sprintf('PAYBEAR_%s_RATES', strtoupper($currency->iso_code));
            $ratesTimestampKey = sprintf('%s_TIMESTAMP', $ratesKey);
            $ratesString = Configuration::get($ratesKey);
            $ratesTimestamp = (int) Configuration::get($ratesTimestampKey);

            if ($ratesString && $ratesTimestamp) {
                $ratesDeadline = $ratesTimestamp + Configuration::get('PAYBEAR_EXCHANGE_LOCKTIME') * 60;
                if ($ratesDeadline < time()) {
                    $needUpdate = true;
                }
            }


            if (!$needUpdate && !empty($ratesString)) {
                self::$rates = json_decode($ratesString);
            } else {
                $url = sprintf("%s/exchange/%s/rate", $this->baseUrl, strtolower($currency->iso_code));

                if ($response = Tools::file_get_contents($url)) {
                    $response = json_decode($response);
                    if ($response->success) {
                        Configuration::updateValue($ratesKey, json_encode($response->data));
                        Configuration::updateValue($ratesTimestampKey, time());
                        self::$rates = $response->data;
                    }
                }
            }
        }

        return self::$rates;
    }

    /**
     * @param string $token
     *
     * @return string
     */
    public static function sanitizeToken($token) {
        $token = strtolower($token);
        $token = preg_replace('/[^a-z0-9:]/', '', $token);

        return $token;
    }
}
