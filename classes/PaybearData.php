<?php


class PaybearData extends ObjectModel
{
    public $id_paybear;

    public $order_reference;

    public $confirmations;

    public $address;

    public $invoice;

    public $amount;

    public $date_add;

    public $date_upd;

    public $token;

    public $payment_add;

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
            'address' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'invoice' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'amount' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => true),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat'),
            'payment_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat'),
        )
    );

    public static function getByOrderRefenceAndToken($orderReference, $token)
    {
        $sql = new DbQuery();
        $sql->select('id_paybear');
        $sql->from(self::$definition['table']);
        $sql->where('order_reference = "'.pSQL($orderReference). '"');
        $sql->where('token = "' . pSQL($token). '"');

        $raw = Db::getInstance()->getRow($sql);
        if ($raw && isset($raw['id_paybear'])) {
            return new self($raw['id_paybear']);
        }

        return null;
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
}
