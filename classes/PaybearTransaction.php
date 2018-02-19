<?php

class PaybearTransaction extends ObjectModel
{
    public $id_paybear_transaction;

    public $order_reference;

    public $invoice;

    public $blockchain;

    public $address;

    public $amount;

    public $currency;

    public $rate;

    public $transaction_hash;

    public $confirmations;

    public $max_confirmations;

    public $date_add;

    public $date_upd;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'paybear_transaction',
        'primary' => 'id_paybear_transaction',
        'multilang' => false,
        'fields' => array(
            'order_reference' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isString'),
            'invoice' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'blockchain' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isString'),
            'address' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'amount' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => true),
            'currency' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'rate' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice'),
            'transaction_hash' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'confirmations' => array('type' => self::TYPE_INT, 'required' => false, 'validate' => false, 'allow_null' => true),
            'max_confirmations' => array('type' => self::TYPE_INT, 'required' => false, 'validate' => false, 'allow_null' => true),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat'),
        )
    );

    /**
     * @param $hash
     *
     * @return PaybearTransaction
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getByTransactionHash($hash)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from(self::$definition['table']);
        $sql->where('transaction_hash = "'.pSQL($hash). '"');

        $result = Db::getInstance()->getRow($sql);
        $object = null;

        if ($result) {
            $object = new self();
            $object->hydrate($result);
        }

        return $object;
    }


}
