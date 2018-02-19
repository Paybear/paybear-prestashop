<?php

if (!defined('_PS_VERSION_'))
    exit;

include_once(_PS_MODULE_DIR_ . 'paybear/classes/PaybearTransaction.php');

function upgrade_module_0_6_0($module) {
    $sql = new DbQuery();
    $sql->select('*');
    $sql->from('paybear_data');
    $oldData = Db::getInstance()->executeS($sql);

    Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'paybear_transaction` (
              `id_paybear_transaction` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
              `order_reference` VARCHAR(9) NOT NULL,
              `invoice` VARCHAR(255) NULL DEFAULT NULL,
              `blockchain` VARCHAR(255) NULL DEFAULT NULL,
              `address` VARCHAR(255) NULL DEFAULT NULL,
              `amount` DECIMAL(20, 8),
              `currency` VARCHAR(255) NULL DEFAULT NULL,
              `rate` DECIMAL(20, 8) NULL DEFAULT NULL,
              `confirmations` INT(2) NULL DEFAULT NULL,
              `max_confirmations` INT(2) NULL DEFAULT NULL,
              `date_add` DATETIME NULL DEFAULT NULL,
              `date_upd` DATETIME NULL DEFAULT NULL,
              KEY `order_reference` (`order_reference`),
              KEY `blockchain` (`blockchain`),
              KEY `transaction_hash` (`transaction_hash`)
        ) ENGINE = '._MYSQL_ENGINE_);

    foreach ($oldData as $row) {
        /** @var Order $order */
        $order = Order::getByReference($row['order_reference'])->getFirst();

        if ($order->current_state != Configuration::get('PS_OS_PAYMENT')) {
            continue;
        }

        $transaction = new PaybearTransaction();
        $transaction->order_reference = $row['order_reference'];
        $transaction->invoice = $row['invoice'];
        $transaction->blockchain = $row['token'];
        $transaction->address = $row['address'];
        $transaction->amount = $row['amount'];
        $transaction->confirmations = $row['confirmations'];
        $transaction->max_confirmations = $row['max_confirmations'];
        $transaction->date_add = $row['payment_add'];
        $transaction->date_upd = $row['payment_add'];
        $transaction->save();
    }

    Configuration::updateValue('PAYBEAR_MAX_UNDERPAYMENT', '0.01');
    Configuration::updateValue('PAYBEAR_MIN_OVERPAYMENT', '1');

    return true;
}
