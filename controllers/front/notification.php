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

require_once(dirname(__FILE__) . '/../../classes/CoinpaysNotification.php');

class CoinpaysCheckoutNotificationModuleFrontController extends ModuleFrontController
{
    private $uniq_separator = 'COINPAYSYPS';

    public function postProcess()
    {
        if (!isset($_POST) or empty(Tools::getValue('hash'))) {
            echo 'no post method';
            exit;
        }

        $notification = new CoinpaysNotification();
        $notification->context = Context::getContext();

        $notification->module = $this->module;
        $notification->iframeCallback($_POST, $this->uniq_separator);
    }
}
