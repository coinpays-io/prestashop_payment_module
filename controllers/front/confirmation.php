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

require_once(dirname(__FILE__) . '/../../classes/CoinpaysTransaction.php');

class CoinpaysCheckoutConfirmationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'coinpayscheckout') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->l('This payment method is not available.'));
        }

        $otoken = Tools::getValue('otoken');
        if ($otoken == null) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Get CoinPays Transaction
        $coinpays_transaction = CoinpaysTransaction::getTransactionByoTokenByDupCartId(urldecode($otoken), $this->context->cart->id);

        if (!$coinpays_transaction) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // If completed before
        if ($coinpays_transaction['is_notify_order'] && $coinpays_transaction['is_order']) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check order if already placed before.
        // Some Prestashop users' systems creating orders when payment page.
        $ps_order_id = $this->checkOrder($coinpays_transaction['id_oid_cart']);

        if ($ps_order_id) {
            $result = $this->completeSteps($coinpays_transaction['id_oid_cart'], $otoken);

            // Redirect
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $result['cart_id'] . '&id_module=' . $this->module->id . '&id_order=' . $ps_order_id . '&key=' . $result['customer_secure_key']);
            exit;
        }

        // If completed by notification then the order must be created in the notification
        if ($coinpays_transaction['is_notify_order'] && $coinpays_transaction['is_complete'] && !$coinpays_transaction['is_order']) {
            $order_id = $this->checkOrder($coinpays_transaction['id_oid_cart']);

            if (!$order_id) {
                Tools::redirect('index.php?controller=order&step=1');
            }

            $result = $this->completeSteps($coinpays_transaction['id_oid_cart'], $otoken);

            // Redirect
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $result['cart_id'] . '&id_module=' . $this->module->id . '&id_order=' . $order_id . '&key=' . $result['customer_secure_key']);
            exit;
        }

        // If it comes to here and if order created before for some reason
        $order_id = $this->checkOrder($coinpays_transaction['id_oid_cart']);

        if ($order_id) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $cart = new Cart($coinpays_transaction['id_oid_cart']);

        if ($cart->id_customer != $this->context->cart->id_customer) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        // Delete Current Cart
        $this->context->cart->delete();

        // Place Order
        $this->module->validateOrder($cart->id, Configuration::get('COINPAYS_PENDING'), $total, $this->module->displayName, null, null, (int)$currency->id, false, $customer->secure_key);

        // Update Transaction
        CoinpaysTransaction::updateTransactionIsOrderByCartIdByoToken($cart->id, urldecode($otoken));

        // Redirect
        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key);
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

    protected function completeSteps($id_oid_cart, $otoken)
    {
        $cart = new Cart($id_oid_cart);

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Delete Current Cart
        $this->context->cart->delete();

        // Update Transaction
        CoinpaysTransaction::updateTransactionIsOrderByCartIdByoToken($cart->id, urldecode($otoken));

        return array('cart_id' => $cart->id, 'customer_secure_key' => $customer->secure_key);
    }
}
