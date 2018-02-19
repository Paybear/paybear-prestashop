<?php

include_once(_PS_MODULE_DIR_ . 'paybear/classes/PaybearTransaction.php');

class PaybearData extends ObjectModel
{
    public $id_paybear;

    public $order_reference;

    public $confirmations;

    public $address;

    public $invoice;

    public $amount;

    // public $paid_amount;

    public $date_add;

    public $date_upd;

    public $token;

    // public $payment_add;

    public $max_confirmations;

    // public $transaction_hash;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'paybear_data',
        'primary' => 'id_paybear',
        'multilang' => false,
        'fields' => array(
            'order_reference' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isString'),
            'token' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isString'),
            'confirmations' => array('type' => self::TYPE_INT, 'required' => false, 'validate' => false, 'allow_null' => true),
            'max_confirmations' => array('type' => self::TYPE_INT, 'required' => false, 'validate' => false, 'allow_null' => true),
            'address' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'invoice' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'amount' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => true),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat'),
            // 'payment_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat'),
            // 'paid_amount' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice'),
            // 'transaction_hash' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
        )
    );

    /**
     * @param $orderReference
     * @param $token
     *
     * @return self[]
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function findAllByOrderRefenceAndToken($orderReference, $token)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from(self::$definition['table']);
        $sql->where('order_reference = "'.pSQL($orderReference). '"');
        $sql->where('token = "' . pSQL($token). '"');

        $result = Db::getInstance()->executeS($sql);
        $objects = [];
        foreach ($result as $row) {
            $object = new self();
            $object->hydrate($row);

            $objects[] = $object;
        }

        return $objects;
    }

    /**
     * @param $orderReference
     *
     * @return self[]
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function findAllByOrderReference($orderReference)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from(self::$definition['table']);
        $sql->where('order_reference = "'.pSQL($orderReference). '"');

        $result = Db::getInstance()->executeS($sql);
        $objects = [];
        foreach ($result as $row) {
            $object = new self();
            $object->hydrate($row);

            $objects[] = $object;
        }

        return $objects;
    }

    public static function getByOrderRefence($orderReference)
    {
        $sql = new DbQuery();
        $sql->select('id_paybear');
        $sql->from(self::$definition['table']);
        $sql->where('order_reference = "'.pSQL($orderReference). '"');

        $raw = Db::getInstance()->getRow($sql);
        if ($raw && isset($raw['id_paybear'])) {
            return new self($raw['id_paybear']);
        }

        return null;
    }

    /**
     * @return PaybearTransaction[]
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getPayments($excludeHash = null)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('paybear_transaction');
        $sql->where('order_reference = "' . pSQL($this->order_reference) . '"');

        if ($excludeHash) {
            $sql->where('transaction_hash != "'. pSQL($excludeHash) .'"');
        }

        $result = Db::getInstance()->executeS($sql);
        $objects = [];

        foreach ($result as $row) {
            $object = new PaybearTransaction();
            $object->hydrate($row);

            $objects[$row['transaction_hash']] = $object;
        }

        return $objects;
    }
}
