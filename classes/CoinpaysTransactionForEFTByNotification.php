<?php
/**
 * 2007-2020 PrestaShop and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    CoinPays Crypto Payment Gateway. <info@coinpays.io>
 * @copyright CoinPays Crypto Payment Gateway.
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of CoinPays Crypto Payment Gateway
 */

class CoinpaysTransactionForEFTByNotification
{
    public $id_coinpays;
    public $id_oid_cart;
    public $id_dup_cart;
    public $order_token;
    public $merchant_oid;
    public $total;
    public $total_paid;
    public $status;
    public $status_message;
    public $interim_message;
    public $is_complete;
    public $is_order;
    public $is_notify_order;
    public $is_refunded;
    public $refund_status;
    public $refund_amount;
    public $date_added;
    public $date_updated;

    # Save Transaction
    public function addTransaction()
    {
        return DB::getInstance()->insert('coinpays_eft_transaction', array(
            'id_oid_cart' => (int)$this->id_oid_cart,
            'id_dup_cart' => (int)$this->id_dup_cart,
            'order_token' => pSQL($this->order_token),
            'merchant_oid' => pSQL($this->merchant_oid),
            'total' => (float)$this->total,
            'total_paid' => (float)$this->total_paid,
            'status' => pSQL($this->status),
            'status_message' => pSQL($this->status_message),
            'interim_message' => pSQL($this->interim_message),
            'is_complete' => (int)$this->is_complete,
            'is_order' => (int)$this->is_order,
            'is_notify_order' => (int)$this->is_notify_order,
            'is_refunded' => (int)$this->is_refunded,
            'refund_status' => pSQL($this->refund_status),
            'refund_amount' => (float)$this->refund_amount,
            'date_added' => $this->date_added,
            'date_updated' => $this->date_updated,
        ));
    }

    # Get Transaction by oToken By Duplicated Cart Id
    public static function getTransactionByoTokenByDupCartId($order_token, $id_dup_cart)
    {
        $order_token = pSQL($order_token);
        $id_dup_cart = (int)$id_dup_cart;
        return Db::getInstance()->getRow("SELECT * FROM `" . _DB_PREFIX_ . "coinpays_eft_transaction` WHERE `order_token` = '{$order_token}' AND `id_dup_cart` = '{$id_dup_cart}'");
    }

    # Get Transaction by id_oid_cart, by merchant_oid
    public static function getTransactionByIdOidCartByOid($id_cart, $oid)
    {
        $id_cart = (int)$id_cart;
        $merchant_oid = pSQL($oid);
        return Db::getInstance()->getRow("SELECT * FROM `" . _DB_PREFIX_ . "coinpays_eft_transaction` WHERE `id_oid_cart` = {$id_cart} AND `merchant_oid` = '{$merchant_oid}'");
    }

    # Get Transactions by Cart Id
    public static function getTransactionsByCart($id_cart)
    {
        $id_cart = (int)$id_cart;
        return Db::getInstance()->executeS("SELECT * FROM `" . _DB_PREFIX_ . "coinpays_eft_transaction` WHERE `id_oid_cart` = {$id_cart}");
    }

    # Get Transaction by Cart Id
    public static function getTransactionByCart($id_cart)
    {
        $id_cart = (int)$id_cart;
        return Db::getInstance()->getRow("SELECT * FROM `" . _DB_PREFIX_ . "coinpays_eft_transaction` 
        WHERE `id_oid_cart` = {$id_cart}");
    }

    # Update Transaction => is_order = 1
    public static function updateTransactionIsOrderByCartIdByoToken($id_cart, $order_token)
    {
        $id_cart = (int)$id_cart;
        $order_token = pSQL($order_token);
        $date = date('Y-m-d H:i:s');
        Db::getInstance()->execute("UPDATE `" . _DB_PREFIX_ . "coinpays_eft_transaction` SET `is_order` = 1, `date_updated` = '{$date}' WHERE `id_oid_cart` = {$id_cart} AND `order_token` = '{$order_token}'");
    }

    # Update Transaction => is_complete = 1 & status = 'completed' or 'done'
    # If when the notification comes before accessing the confirmation page, then is_notify_order = 1
    public static function updateTransactionIsComplete($id_cart, $merchant_oid, $status, $status_message, $total_paid, $is_notify_order = 0)
    {
        $id_cart = (int)$id_cart;
        $is_notify_order = (int)$is_notify_order;
        $is_order = 1;

        if ($is_notify_order == 1) {
            $is_order = 0;
        }

        $status = pSQL($status);
        $status_message = pSQL($status_message);
        $total_paid = pSQL($total_paid);
        $date = date('Y-m-d H:i:s');
        Db::getInstance()->execute("UPDATE `" . _DB_PREFIX_ . "coinpays_eft_transaction` SET 
        `is_complete` = 1, 
        `is_order` = {$is_order}, 
        `is_notify_order` = {$is_notify_order}, 
        `status` = '{$status}', 
        `status_message` = '{$status_message}', 
        `total_paid` = {$total_paid}, 
        `date_updated` = '{$date}' 
        WHERE `id_oid_cart` = {$id_cart} 
        AND `merchant_oid` = '{$merchant_oid}'");
    }

    # Update Transaction => is_refund = 1
    public static function updateTransactionIsRefund($merchant_oid, $amount, $refund_status)
    {
        $oid = pSQL($merchant_oid);
        $amount = pSQL($amount);
        $refund_status = pSQL($refund_status);
        $date = date('Y-m-d H:i:s');
        return Db::getInstance()->execute("UPDATE `" . _DB_PREFIX_ . "coinpays_eft_transaction` SET 
        `is_refunded` = 1, 
        `refund_amount` = '{$amount}',
        `refund_status` = '{$refund_status}',
        `date_updated` = '{$date}' 
        WHERE `merchant_oid` = '{$oid}'");
    }
}
