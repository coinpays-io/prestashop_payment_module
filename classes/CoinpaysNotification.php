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

require_once(dirname(__FILE__) . '/CoinpaysTransaction.php');
require_once(dirname(__FILE__) . '/CoinpaysTransactionForEFTByNotification.php');

class CoinpaysNotification
{
    public $context;
    public $module;

    public function iframeCallback($params, $uniq_separator)
    {
        // Check Hash
        $this->checkHash($params, 'iframe');

        // Get ID Cart
        $id_cart = explode($uniq_separator, $params['merchant_oid'], 2);

        // Get CoinPays Transaction
        $coinpays_transaction = CoinpaysTransaction::getTransactionByIdOidCartByOid($id_cart[1], $params['merchant_oid']);

        if (!$coinpays_transaction) {
            die('OK');
        }

        // If transaction is already completed
        if ($coinpays_transaction['is_complete'] && $coinpays_transaction['status'] == 'success' || $coinpays_transaction['status'] == 'failed') {
            echo 'OK';
            exit;
        }

        // Get Configurations
        $conf_pending = Configuration::get('COINPAYS_PENDING');
        $conf_canceled = Configuration::get('COINPAYS_CANCELED');
        $conf_completed = Configuration::get('COINPAYS_COMPLETED');
        $conf_invoice = Configuration::get('COINPAYS_INVOICE');

        // Amounts
        // Total Paid (Included installment difference if exist)
        // Payment Amount (Cart Total)
        $total_amount = round($params['total_amount'], 2);
        $payment_amount = $total_amount;

        $val = array(
            'status' => $params['status'],
            'total_amount' => $total_amount,
            'payment_amount' => $payment_amount,
            'conf_pending' => $conf_pending,
            'conf_completed' => $conf_completed,
            'conf_canceled' => $conf_canceled,
            'conf_invoice' => $conf_invoice,
            'merchant_oid' => $params['merchant_oid'],
            'payment_type' => 'crypto'
        );

        if ($params['status'] == 'failed') {
            $val['failed_reason_code'] = $params['failed_reason_code'];
            $val['failed_reason_msg'] = $params['failed_reason_msg'];
        }

        // Check order if already placed before.
        // Some Prestashop users' systems creating orders when payment page.
        $ps_order_id = $this->checkOrder((int)$id_cart[1]);

        if ($ps_order_id) {
            $order = new Order($ps_order_id);

            // check order status
            if ($order->current_state != $conf_pending) {
                // State 12 = Out of Stock (Pre-Order Not Paid)
                if ($params['status'] == 'success' && $order->current_state == 12) {
                    $order->setCurrentState(9); // Out of Stock (Pre-Order Paid)
                    CoinpaysTransaction::updateTransactionIsComplete($coinpays_transaction['id_oid_cart'], $params['merchant_oid'], 'success', 'completed', $total_amount, 0);
                } else {
                    CoinpaysTransaction::updateTransactionIsComplete($coinpays_transaction['id_oid_cart'], $params['merchant_oid'], 'success', 'done', $total_amount, 0);
                }

                echo 'OK';
                exit;
            }

            $this->updateStatus($val, $order, $id_cart[1], 0, 'iframe');
        }

        if ($coinpays_transaction['is_order'] && !$coinpays_transaction['is_complete']) {
            // Get Order By id_oid_cart
            $order_id = $this->checkOrder((int)$id_cart[1]);

            if (!$order_id) {
                die('OK');
            }

            $order = new Order($order_id);

            // check order status
            if ($order->current_state != $conf_pending) {
                // State 12 = Out of Stock (Pre-Order Not Paid)
                if ($params['status'] == 'success' && $order->current_state == 12) {
                    $order->setCurrentState(9); // Out of Stock (Pre-Order Paid)
                    CoinpaysTransaction::updateTransactionIsComplete($coinpays_transaction['id_oid_cart'], $params['merchant_oid'], 'success', 'completed', $total_amount, 0);
                } else {
                    CoinpaysTransaction::updateTransactionIsComplete($coinpays_transaction['id_oid_cart'], $params['merchant_oid'], 'success', 'done', $total_amount, 0);
                }

                echo 'OK';
                exit;
            }

            $this->updateStatus($val, $order, $id_cart[1], 0, 'iframe');
        } elseif (!$coinpays_transaction['is_order']) {
            if ($params['status'] == 'failed' && $params['failed_reason_code'] == 6) {
                echo 'OK';
                exit;
            }

            // Get Cart
            $cart = new Cart($coinpays_transaction['id_oid_cart']);

            $currency = new Currency($cart->id_currency);
            $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
            $customer = new Customer($cart->id_customer);

            // Create New Order
            $this->module->validateOrder($cart->id, $conf_pending, $total, $this->module->displayName, null, null, (int)$currency->id, false, $customer->secure_key);

            $order = new Order($this->module->currentOrder);

            $this->updateStatus($val, $order, $id_cart[1], 1, 'iframe');
        }
    }

    protected function checkOrder($id_cart)
    {
        $order_id = 0;

        if (version_compare(_PS_VERSION_, '1.7.1.0', '>=')) {
            $order_id = Order::getIdByCartId((int)$id_cart);
        } else {
            $order_id = Order::getOrderByCartId((int)$id_cart);
        }

        return $order_id;
    }

    protected function checkHash($params, $api_name)
    {
        $merchant = array();
        $merchant['merchant_key'] = Configuration::get('COINPAYS_MERCHANT_KEY');
        $merchant['merchant_salt'] = Configuration::get('COINPAYS_MERCHANT_SALT');
        $created_hash = base64_encode(hash_hmac('sha256', $params['merchant_oid'] . $merchant['merchant_salt'] . $params['status'] . $params['total_amount'], $merchant['merchant_key'], true));

        if ($created_hash != $params['hash']) {
            die('COINPAYS notification failed: bad hash.');
        }

        return true;
    }

    protected function updateStatus(array $val, $order, $id_cart, $is_notify, $api_name)
    {
        // Update Order Status
        if ($val['status'] == 'success') {
            if ($order->current_state == $val['conf_pending']) {
                $order->setCurrentState($val['conf_completed']);
            }

            if ($order->current_state == 12) {
                $order->setCurrentState(9);
            }
            CoinpaysTransaction::updateTransactionIsComplete($id_cart, $val['merchant_oid'], $val['status'], 'completed', $val['total_amount'], $is_notify);
        } elseif ($val['status'] == 'failed' && $val['failed_reason_code'] != '6') {
            $order->setCurrentState($val['conf_canceled']);

            // Update Transaction
            $status_message = $val['failed_reason_code'] . " - " . $val['failed_reason_msg'];
            CoinpaysTransaction::updateTransactionIsComplete($id_cart, $val['merchant_oid'], $val['status'], $status_message, 0);
        }

        echo 'OK';
        exit;
    }
}
