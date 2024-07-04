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

<div class="row">
    <div class="col-xs-12">
        <p class="payment_module coinpays">
            <a href="{$link->getModuleLink('coinpayscheckout', 'payment')|escape:'htmlall':'UTF-8'}"
               title="{$ps.method_title|escape:'htmlall':'UTF-8'}">
                {$ps.method_title|escape:'htmlall':'UTF-8'}
            </a>
        </p>
    </div>
</div>