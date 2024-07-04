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

{capture name=path}
    <span class="navigation_page">{l s='COINPAYS PAYMENT' mod='coinpayscheckout'}</span>
{/capture}

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if $ps.nbProducts <= 0}
    <p class="warning">{l s='Your shopping cart is empty.' mod='coinpayscheckout'}</p>
{else}
    {if $ps.errors}
        {foreach $ps.errors as $err}
            <p class="alert alert-danger">
                {$err|escape:'htmlall':'UTF-8'}
            </p>
        {/foreach}
    {else}
        <section>
            <script src="https://app.coinpays.io/assets/js/iframeResizer.min.js"></script>
            <iframe src="https://app.coinpays.io/payment/{$ps.token|escape:'htmlall':'UTF-8'}" id="coinpaysiframe" frameborder="0" scrolling="no" style="width: 100%;"></iframe>
            <script type="text/javascript">
                setInterval(function () {
                    iFrameResize({}, '#coinpaysiframe');
                }, 1000);
            </script>
        </section>
        <p class="cart_navigation clearfix" id="cart_navigation">
            <a class="button-exclusive btn btn-default"
               href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'htmlall':'UTF-8'}">
                <i class="icon-chevron-left"></i>{l s='Other payment methods' mod='coinpayscheckout'}
            </a>
        </p>
    {/if}
{/if}