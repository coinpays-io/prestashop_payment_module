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

class CoinpaysCheckoutPaymentModuleFrontController extends ModuleFrontController
{
    protected $category_full = array();
    protected $category_installment = array();

    public function initContent()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;

        parent::initContent();

        $errors = null;
        $iframe_token = null;
        $coinpays_params = array();
        $user_basket = array();

        if (Configuration::get('COINPAYS_INSTALLMENT') != 13) {
            foreach ($this->context->cart->getProducts() as $product) {
                $user_basket[] = array($product['name'], $product['total_wt'], $product['cart_quantity']);
            }

            $coinpays_params['max_installment'] = in_array(Configuration::get('COINPAYS_INSTALLMENT'), range(0, 12)) ? Configuration::get('COINPAYS_INSTALLMENT') : 0;
        } else {
            $installment = array();
            $conf_ci = Configuration::get('COINPAYS_CI');

            if ($conf_ci) {
                $this->category_installment = unserialize(Configuration::get('COINPAYS_CI'));

                foreach ($this->context->cart->getProducts() as $product) {
                    $user_basket[] = array($product['name'], $product['total_wt'], $product['cart_quantity']);
                    $query = Db::getInstance()->ExecuteS("SELECT id_category as 'category_id' FROM `" . _DB_PREFIX_ . "category_product` WHERE id_product = '" . $product['id_product'] . "'");

                    foreach ($query as $id => $item) {
                        if (array_key_exists($item['category_id'], $this->category_installment)) {
                            $installment[$item['category_id']] = $this->category_installment[$item['category_id']];
                        } else {
                            $installment[$item['category_id']] = $this->catSearch($item['category_id']);
                        }
                    }
                }

                $installment = count(array_diff($installment, array(0))) > 0 ? min(array_diff($installment, array(0))) : 0;
                $coinpays_params['max_installment'] = $installment ? $installment : 0;
            } else {
                $coinpays_params['max_installment'] = 0;
            }
        }

        $coinpays_params['no_installment'] = ($coinpays_params['max_installment'] == 1) ? 1 : 0;

        $coinpays_params['merchant_id'] = trim(Configuration::get('COINPAYS_MERCHANT_ID'));
        $coinpays_params['merchant_key'] = trim(Configuration::get('COINPAYS_MERCHANT_KEY'));
        $coinpays_params['merchant_salt'] = trim(Configuration::get('COINPAYS_MERCHANT_SALT'));

        $customer = new Customer($this->context->cart->id_customer);
        $address = new Address($this->context->cart->id_address_invoice);

        $uniq_separator = 'COINPAYSYPS';

        $coinpays_params['user_ip'] = $this->getIp() == '::1' || $this->getIp() == '127.0.0.1' ? '85.105.186.196' : $this->getIp();
        $coinpays_params['oid'] = uniqid() . $uniq_separator . $this->context->cart->id;
        $coinpays_params['email'] = $customer->email;
        $coinpays_params['payment_amount'] = $this->context->cart->getOrderTotal(true, Cart::BOTH) * 100;
        $coinpays_params['user_basket'] = base64_encode(json_encode($user_basket));
        $coinpays_params['user_name'] = $customer->firstname . ' ' . $customer->lastname;
        $coinpays_params['user_address'] = $address->address1 . ' ' . $address->address2 . ' ' . $address->postcode . ' ' . $address->city . ' ' . $address->country;
        $coinpays_params['user_phone'] = $address->phone_mobile ? $address->phone_mobile : $address->phone;

        $currencyCore = new Currency($this->context->cart->id_currency);
        $currency = Tools::strtoupper($currencyCore->iso_code);

        $hash_str = $coinpays_params['merchant_id'] . $coinpays_params['user_ip'] . $coinpays_params['oid'] . $coinpays_params['email'] . $coinpays_params['payment_amount'] . $coinpays_params['user_basket'];
        $coinpays_token = base64_encode(hash_hmac('sha256', $hash_str . $coinpays_params['merchant_salt'], $coinpays_params['merchant_key'], true));

        $lang_arr = array('tr', 'tr-tr', 'tr_tr', 'turkish', 'turk', 'türkçe', 'turkce', 'try', 'tl');

        $random = md5(uniqid(mt_rand(), true));
        $order_token = base64_encode(hash_hmac('sha256', $random, $coinpays_params['merchant_id'], true));

        $post_data = array(
            'merchant_id' => $coinpays_params['merchant_id'],
            'user_ip' => $coinpays_params['user_ip'],
            'lang' => (in_array(Tools::strtolower($this->context->language->iso_code), $lang_arr) ? 'tr' : 'en'),
            'currency' => $currency,
            'merchant_oid' => $coinpays_params['oid'],
            'email' => $coinpays_params['email'],
            'payment_amount' => $coinpays_params['payment_amount'],
            'coinpays_token' => $coinpays_token,
            'user_basket' => $coinpays_params['user_basket'],
            'user_name' => $coinpays_params['user_name'],
            'user_address' => $coinpays_params['user_address'],
            'user_phone' => $coinpays_params['user_phone'],
            'merchant_pending_url' => $this->context->link->getModuleLink('coinpayscheckout', 'confirmation', array('otoken' => urlencode($order_token))),
            'test_mode' => 0,
        );

        if (function_exists('curl_version')) {
            /*
            * XXX: DİKKAT: lokal makinanızda "SSL certificate problem: unable to get local issuer certificate" uyarısı alırsanız eğer
            * aşağıdaki kodu açıp deneyebilirsiniz. ANCAK, güvenlik nedeniyle sunucunuzda (gerçek ortamınızda) bu kodun kapalı kalması çok önemlidir!
            * curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            */
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://app.coinpays.io/api/get-token");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 90);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 90);
            curl_setopt($ch, CURLOPT_SSLVERSION, 6);
            $result = @curl_exec($ch);

            if (curl_errno($ch)) {
                $errors = "COINPAYS IFRAME connection error. err: " . curl_error($ch);
            } else {
                curl_close($ch);
                $result = json_decode($result, 1);

                if ($result['status'] == 'success') {
                    $iframe_token = $result['token'];

                    // Save Transaction
                    $coinpays_transaction = new CoinpaysTransaction();
                    $coinpays_transaction->id_oid_cart = $this->context->cart->id;
                    $coinpays_transaction->order_token = $order_token;
                    $coinpays_transaction->merchant_oid = $coinpays_params['oid'];
                    $coinpays_transaction->total = $this->context->cart->getOrderTotal(true, Cart::BOTH);
                    $coinpays_transaction->total_paid = 0;
                    $coinpays_transaction->is_complete = 0;
                    $coinpays_transaction->is_order = 0;
                    $coinpays_transaction->is_notify_order = 0;
                    $coinpays_transaction->is_refunded = 0;
                    $coinpays_transaction->refund_amount = 0;

                    $oldCart = new Cart((int)$this->context->cart->id);

                    // Duplicate Cart
                    $duplication = $oldCart->duplicate();
                    if (!$duplication or !Validate::isLoadedObject($duplication['cart'])) {
                        $this->errors[] = Tools::displayError('Sorry, we cannot renew your order.');
                    } elseif (!$duplication['success']) {
                        $this->errors[] = Tools::displayError('Missing items - we are unable to renew your order');
                    } else {
                        $this->context->cookie->id_cart = $duplication['cart']->id;
                        $this->context->cart = $duplication['cart'];
                        $this->context->cookie->write();
                    }

                    $coinpays_transaction->id_dup_cart = $duplication['cart']->id;
                    $coinpays_transaction->date_added = date('Y-m-d H:i:s');
                    $coinpays_transaction->date_updated = date('Y-m-d H:i:s');
                    $coinpays_transaction->addTransaction();

                } else {
                    $errors = "COINPAYS IFRAME failed. reason:" . $result['reason'];
                }
            }
        } else {
            $errors = 'CURL Problem!';
        }

        $this->context->smarty->assign('ps', array(
            'nbProducts' => $this->context->cart->nbProducts(),
            'cust_currency' => $this->context->cart->id_currency,
            'currencies' => $this->module->getCurrency((int)$this->context->cart->id_currency),
            'errors' => $errors,
            'token' => $iframe_token,
            'this_path' => $this->module->getPathUri(),
            'this_path_bw' => $this->module->getPathUri(),
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->module->name . '/',
        ));

        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $this->setTemplate('module:coinpayscheckout/views/templates/front/payment_iframe_plus.tpl');
        } else {
            $this->setTemplate('payment_iframe.tpl');
        }
    }

    public function catSearch($category_id = 0)
    {
        $return = 0;

        if (!empty($this->category_full[$category_id]) and array_key_exists($this->category_full[$category_id], $this->category_installment)) {
            $return = $this->category_installment[$this->category_full[$category_id]];
        } else {
            foreach ($this->category_full as $id => $parent) {
                if ($category_id == $id) {
                    if ($parent == 0) {
                        $return = 0;
                    } elseif (array_key_exists($parent, $this->category_installment)) {
                        $return = $this->category_installment[$parent];
                    } else {
                        $return = $this->catSearch($parent);
                    }
                } else {
                    $return = 0;
                }
            }
        }

        return ($return ? $return : 0);
    }

    public function getIp()
    {
        if (isset($_SERVER["HTTP_CLIENT_IP"])) {
            $ip = $_SERVER["HTTP_CLIENT_IP"];
        } elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else {
            $ip = $_SERVER["REMOTE_ADDR"];
        }
        return $ip;
    }
}
