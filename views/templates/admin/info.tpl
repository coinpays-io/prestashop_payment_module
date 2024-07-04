{*
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
*}

<div class="panel row">
    <div class="col-md-2 text-center">
        <img src="{$img|escape:'htmlall':'UTF-8'}" width="140" />
    </div>
    <div class="col-md-10">
        <p>{l s='This module allows you to accept secure payments with crypto tokens' mod='coinpayscheckout'}.</p>
        <p>{l s='Store information can be found at this address' mod='coinpayscheckout'}. <a href="https://app.coinpays.io/manage/installations/integration" target="_blank">app.coinpays.io/manage/installations/integration</a></p>
        <p>{l s='Callback URL:' mod='coinpayscheckout'} <code>{$coinpays_callback_url|escape:'htmlall':'UTF-8'}</code></p>
    </div>
</div>