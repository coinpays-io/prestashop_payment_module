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

class CoinpaysCheckoutRefundModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $this->ajax = true;

        parent::initContent();
    }

    public function displayAjaxTransactions()
    {
        if (_PS_MODE_DEMO_) {
            throw new PrestaShopException($this->trans('This functionality has been disabled.', array(), 'Admin.Notifications.Error'));
        }

        if (!Tools::isSubmit('submitCoinpaysRefundTable')) {
            $this->renderSubmitControl();
            exit;
        }

        $all_transaction = array();
        $content = '';
        $id_order = Tools::getValue('id_order');
        $order = new Order($id_order);
        $transactions = CoinpaysTransaction::getTransactionsByCart($order->id_cart);
        $yes = $this->module->l('Yes', 'refund');
        $no = $this->module->l('No', 'refund');
        $success = $this->module->l('Success', 'refund');
        $failed = $this->module->l('Failed', 'refund');
        $partial = $this->module->l('Partial', 'refund');
        $full = $this->module->l('Full', 'refund');

        if (!$transactions) {
            $content .= '<tr><td colspan="10" style="text-align: center;">' . $this->l('The transaction is not yet complete.') . '</td></tr>';
        } else {
            foreach ($transactions as $transaction) {
                $content .= '<tr>';
                $content .= '<td><a href="https://www.coinpays.com/magaza/islemler?merchant_oid=' . $transaction['merchant_oid'] . '" target="_blank">' . $transaction['merchant_oid'] . '</a></td>';
                $content .= '<td>' . $this->renderPrice($transaction['total'], $order->id_currency) . '</td>';
                $content .= '<td>' . $this->renderPrice($transaction['total_paid'], $order->id_currency) . '</td>';
                if ($transaction['status'] == 'success') {
                    $content .= '<td>' . $success . '</td>';
                } elseif ($transaction['status'] == 'failed') {
                    $content .= '<td>' . $failed . '</td>';
                } else {
                    $content .= '<td>' . '' . '</td>';
                }
                $content .= '<td>' . $transaction['status_message'] . '</td>';
                $content .= '<td>' . ($transaction['is_refunded'] == 1 ? $yes : $no) . '</td>';
                $refund_status = '';
                if ($transaction['refund_status'] == 'partial') {
                    $refund_status = $partial;
                } elseif ($transaction['refund_status'] == 'full') {
                    $refund_status = $full;
                }
                $content .= '<td>' . $refund_status . '</td>';
                $content .= '<td>' . $this->renderPrice($transaction['refund_amount'], $order->id_currency) . '</td>';
                $content .= '<td>' . date('d-m-Y H:i', strtotime($transaction['date_added'])) . '</td>';

                if ($transaction['total_paid'] >= 1 && $transaction['refund_status'] != 'full' && $transaction['status_message'] == 'completed') {
                    $amount = $transaction['total'];
                    if ($transaction['refund_amount'] < $transaction['total']) {
                        $amount = $transaction['total'] - $transaction['refund_amount'];
                    }

                    $content .= '<td><button type="button" class="btn btn-warning" data-toggle="modal" data-target="#coinpaysRefundModal" data-amount="' . round($amount, 2) . '">' .
                        $this->module->l('Refund', 'refund') . '</button></td>';
                } else {
                    $content .= '<td></td>';
                }

                $content .= '</tr>';
            }
        }


        $all_transaction['body'] = $content;

        $this->renderContent(json_encode($all_transaction));
    }

    public function displayAjaxRefundApi()
    {
        if (_PS_MODE_DEMO_) {
            throw new PrestaShopException($this->trans('This functionality has been disabled.', array(), 'Admin.Notifications.Error'));
        }

        if (!Tools::isSubmit('submitCoinpaysRefund')) {
            $this->renderSubmitControl();
            exit;
        }

        $response = null;
        $amount = Tools::getValue('amount');
        $id_order = Tools::getValue('id_order');

        if (Tools::isEmpty($id_order)) {
            $this->renderContent(json_encode(array(
                'status' => 'error',
                'err_msg' => $this->l('Order ID is null!')
            )));
            exit;
        }

        if (!Validate::isInt($id_order)) {
            $this->renderContent(json_encode(array(
                'status' => 'error',
                'err_msg' => $this->l('Invalid Order ID!')
            )));
            exit;
        }

        $order = new Order($id_order);

        if (!Validate::isLoadedObject($order)) {
            $this->renderContent(json_encode(array(
                'status' => 'error',
                'err_msg' => $this->l('Order not found.')
            )));
            exit;
        }

        if (Tools::isEmpty($amount)) {
            $this->renderContent(json_encode(array(
                'status' => 'error',
                'err_msg' => $this->l('Amount is null!')
            )));
            exit;
        }

        $amount = str_replace('+', '', $amount);
        $amount = str_replace(',', '.', $amount);

        if (!Validate::isPrice($amount)) {
            $this->renderContent(json_encode(array(
                'status' => 'error',
                'err_msg' => $this->l('Invalid Amount!')
            )));
            exit;
        }

        if (!$amount > 0) {
            $this->renderContent(json_encode(array(
                'status' => 'error',
                'err_msg' => $this->l('Amount must be bigger than 0!')
            )));
            exit;
        }

        $coinpays_transaction = CoinpaysTransaction::getTransactionByCart($order->id_cart);

        if ($coinpays_transaction['is_refunded'] && $coinpays_transaction['refund_status'] == 'partial') {
            $actually_total = $coinpays_transaction['total'] - $coinpays_transaction['refund_amount'];

            if (round($actually_total, 2) < $amount) {
                $this->renderContent(json_encode(array(
                    'status' => 'error',
                    'err_msg' => $this->l('The amount entered cannot be greater than the remaining amount!')
                )));
                exit;
            }
        } else {
            if ($coinpays_transaction['total'] < $amount) {
                $this->renderContent(json_encode(array(
                    'status' => 'error',
                    'err_msg' => $this->l('The amount entered cannot be greater than the remaining amount!')
                )));
                exit;
            }
        }

        $merchant = array();
        $merchant['id'] = Configuration::get('COINPAYS_MERCHANT_ID');
        $merchant['key'] = Configuration::get('COINPAYS_MERCHANT_KEY');
        $merchant['salt'] = Configuration::get('COINPAYS_MERCHANT_SALT');

        $return_amount = $amount;
        $coinpays_token = base64_encode(hash_hmac('sha256', $merchant['id'] . $coinpays_transaction['merchant_oid'] . $return_amount . $merchant['salt'], $merchant['key'], true));

        $post_val = array(
            'merchant_id' => $merchant['id'],
            'merchant_oid' => $coinpays_transaction['merchant_oid'],
            'return_amount' => $return_amount,
            'coinpays_token' => $coinpays_token
        );

        /*
        * XXX: DİKKAT: lokal makinanızda "SSL certificate problem: unable to get local issuer certificate" uyarısı alırsanız eğer
        * aşağıdaki kodu açıp deneyebilirsiniz. ANCAK, güvenlik nedeniyle sunucunuzda (gerçek ortamınızda) bu kodun kapalı kalması çok önemlidir!
        * curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        */
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.coinpays.com/odeme/iade");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_val);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 90);
        $result = @curl_exec($ch);

        if (curl_errno($ch)) {
            $response = json_encode(curl_error($ch));
            curl_close($ch);
        } else {
            curl_close($ch);

            $refund_response = json_decode($result, 1);
            $coinpays_tr_refund_status = 'partial';
            $coinpays_tr_refund_amount = 0;

            if ($refund_response['status'] == 'success') {
                if ($coinpays_transaction['total'] == $amount && $coinpays_transaction['total'] == $refund_response['return_amount']) {
                    $coinpays_tr_refund_status = 'full';
                    $coinpays_tr_refund_amount = $refund_response['return_amount'];
                } else {
                    if ($coinpays_transaction['is_refunded'] && $coinpays_transaction['refund_status'] == 'partial') {
                        $coinpays_tr_refund_amount = $coinpays_transaction['refund_amount'] + $refund_response['return_amount'];

                        if (round($coinpays_tr_refund_amount, 2) == round($coinpays_transaction['total'], 2)) {
                            $coinpays_tr_refund_status = 'full';
                            $coinpays_tr_refund_amount = $coinpays_transaction['total'];
                        }
                    } else {
                        $coinpays_tr_refund_amount = $refund_response['return_amount'];
                    }
                }

                if (Configuration::get('COINPAYS_REFUND')) {
                    $order->setCurrentState(7);
                }

                CoinpaysTransaction::updateTransactionIsRefund($coinpays_transaction['merchant_oid'], $coinpays_tr_refund_amount, $coinpays_tr_refund_status);
            }

            $refund_response['refund_status'] = $coinpays_tr_refund_status;

            $response = json_encode($refund_response);
        }

        $this->renderContent($response);
    }

    protected function renderContent($response)
    {
        die($response);
    }

    protected function renderPrice($price, $currency_id)
    {
        $currency = new Currency($currency_id);

        if (version_compare(_PS_VERSION_, '1.7.6.0', '>=')) {
            return Context::getContext()->currentLocale->formatPrice($price, $currency->iso_code);
        } else {
            return Tools::displayPrice($price, $currency);
        }
    }

    protected function renderSubmitControl()
    {
        $this->renderContent(json_encode(array(
            'status' => 'error',
            'err_msg' => $this->l('Service is not found!')
        )));
    }
}
