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

<style>
    #coinpaysRefundTable thead th:not(:first-child), #coinpaysRefundTable tbody td:not(:first-child) {
        text-align: center;
    }
</style>
<div id="formCoinpaysTransactionPanel" class="panel">
    <div class="panel-heading">
        <img src="{$pt.icon_coinpays|escape:'htmlall':'UTF-8'}"/>
        {l s='TRANSACTIONS' mod='coinpayscheckout'}
        <span class="panel-heading-action">
            <a href="javascript:;" class="list-toolbar-btn" onclick="loadTransactionTable()">
                <span data-toggle="tooltip" class="label-tooltip" data-html="true" data-placement="top">
                    <i class="process-icon-refresh"></i>
                </span>
            </a>
        </span>
    </div>

    <div class="table-responsive">
        <table class="table" id="coinpaysRefundTable">
            <thead>
            <tr>
                <th><span class="title_box">{l s='Order Number' mod='coinpayscheckout'}</span></th>
                <th class="text-center"><span class="title_box">{l s='Total' mod='coinpayscheckout'}</span></th>
                <th class="text-center"><span class="title_box">{l s='Total Paid' mod='coinpayscheckout'}</span></th>
                <th class="text-center"><span class="title_box">{l s='Notification Status' mod='coinpayscheckout'}</span>
                </th>
                <th class="text-center"><span class="title_box">{l s='Status Message' mod='coinpayscheckout'}</span></th>
                <th class="text-center"><span class="title_box">{l s='Refunded' mod='coinpayscheckout'}</span></th>
                <th class="text-center"><span class="title_box">{l s='Refunded Status' mod='coinpayscheckout'}</span></th>
                <th class="text-center"><span class="title_box">{l s='Refunded Amount' mod='coinpayscheckout'}</span></th>
                <th class="text-center"><span class="title_box">{l s='Date' mod='coinpayscheckout'}</span></th>
                <th class="text-center"></th>
            </tr>
            </thead>
            <tbody id="coinpaysRefundTableBody">
            <tr>
                <td colspan="10" align="center">
                    <img src="{$pt.icon_loader|escape:'htmlall':'UTF-8'}" width="30" style="margin: 20px 0;">
                </td>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="coinpaysRefundModal" tabindex="-1" role="dialog" aria-labelledby="coinpaysRefundModalLabel"
     aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <form id="coinpaysRefundForm">
                    <div class="form-group">
                        <label for="coinpaysRefundAmount"
                               class="col-form-label">{l s='Amount of Refund' mod='coinpayscheckout'}:</label>
                        <div class="input-group">
                            <div class="input-group-addon">{$pt.currency_icon|escape:'htmlall':'UTF-8'}</div>
                            <input type="text" class="form-control" id="txtCoinpaysRefundAmount">
                        </div>
                    </div>
                </form>
                <div id="coinpaysRefundResponseBody" style="margin-bottom: 0!important;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btnCloseModal"
                        data-dismiss="modal">{l s='Cancel' mod='coinpayscheckout'}</button>
                <button type="button" id="coinpaysRefundBtn"
                        class="btn btn-warning">{l s='Refund Payment' mod='coinpayscheckout'}</button>
            </div>
        </div>
    </div>
</div>


<script type="text/javascript">

    // <![CDATA[
    // Translations
    var coinpays_error = '{l s='An error occurred.' mod='coinpayscheckout' js=1}';
    var coinpays_error_table_load = '{l s='An error occurred while loading the table.' mod='coinpayscheckout' js=1}';
    var coinpays_error_check_console = '{l s='Check the console(F12) for more info.' mod='coinpayscheckout' js=1}';
    var coinpays_trans_wait = '{l s='Please Wait' mod='coinpayscheckout' js=1}';
    var coinpays_trans_error = '{l s='Error!' mod='coinpayscheckout' js=1}';
    var coinpays_trans_success = '{l s='Success!' mod='coinpayscheckout' js=1}';
    var coinpays_success_refund = '{l s='The refund process is completed successfully.' mod='coinpayscheckout' js=1}';
    var coinpays_btn_refund_payment = '{l s='Refund Payment' mod='coinpayscheckout' js=1}';
    //]]>

    var coinpays_id_order = {$pt.id_order|escape:'htmlall':'UTF-8'},
        coinpays_path = '{$pt.path|escape:'htmlall':'UTF-8'}',
        coinpays_icon_loader = '{$pt.icon_loader|escape:'htmlall':'UTF-8'}';

    function loadTransactionTable() {

        const tableBody = $('#coinpaysRefundTableBody');
        tableBody.html('<tr><td colspan="10" align="center"><img src="' + coinpays_icon_loader + '" width="30" style="margin: 20px 0;"></td></tr>');

        $.ajax({
            url: coinpays_path,
            cache: false,
            type: 'POST',
            data: {
                ajax: 1,
                submitCoinpaysRefundTable: 1,
                token: '{getAdminToken tab="AdminOrders"}',
                action: 'transactions',
                id_order: coinpays_id_order
            },
            success: function (res) {
                res = $.parseJSON(res);
                tableBody.html(res.body);
            },
            error: function (res) {
                tableBody.html("<tr><td colspan='10' align='center'>" + coinpays_error_table_load + "<br/><small>" + coinpays_error_check_console + "</small></td></tr>");
                console.log(res.responseText);
            }
        });
    }

    function resetBtn(btn) {
        btn
            .html(coinpays_btn_refund_payment)
            .attr('disabled', false)
            .removeClass()
            .addClass('btn btn-warning');
    }

    $(window).on('load', function () {
        loadTransactionTable();
    });

    $('#coinpaysRefundModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const modal = $(this);
        modal.find('.modal-body #txtCoinpaysRefundAmount').val(button.data('amount'));
    });

    $(document).on('click', '#coinpaysRefundBtn', function (e) {

        const button = $('#coinpaysRefundBtn');
        const btnModal = $('#btnCloseModal');
        const amount = document.getElementById('txtCoinpaysRefundAmount').value;
        const responseBody = $('#coinpaysRefundResponseBody');

        responseBody
            .html('')
            .removeClass();

        button
            .html('<img src="' + coinpays_icon_loader + '" width="10" style="margin-right: 5px; vertical-align:middle;" />' + coinpays_trans_wait + '')
            .attr('disabled', true)
            .removeClass()
            .addClass('btn btn-disabled');

        btnModal
            .attr('disabled', true);

        $.ajax({
            url: coinpays_path,
            cache: false,
            type: 'POST',
            data: {
                ajax: 1,
                submitCoinpaysRefund: 1,
                token: '{getAdminToken tab="AdminOrders"}',
                action: 'refundApi',
                'amount': amount,
                id_order: coinpays_id_order
            },
            success: function (res) {

                res = $.parseJSON(res);

                if (!res.status) {
                    responseBody
                        .addClass('alert alert-danger')
                        .html('<p>' + coinpays_trans_error + ' ' + res + '</p>');

                    resetBtn(button);
                    btnModal.attr('disabled', false);
                }
                if (res.status === 'error') {
                    responseBody
                        .addClass('alert alert-danger')
                        .html('<p>' + coinpays_trans_error + ' ' + res.err_msg + '</p>');

                    resetBtn(button);
                    btnModal.attr('disabled', false);
                }
                if (res.status === 'success') {
                    responseBody
                        .addClass('alert alert-success')
                        .html('<p>' + coinpays_trans_success + ' ' + coinpays_success_refund + '</p>');

                    setTimeout(function () {
                        $('#coinpaysRefundModal').modal('hide');

                        if (res.refund_status === 'partial') {
                            resetBtn(button);
                            btnModal.attr('disabled', false);
                            responseBody
                                .removeClass()
                                .html('');
                        }
                    }, 1000);

                    loadTransactionTable();
                }
            },
            error: function (res) {

                responseBody
                    .addClass('alert alert-danger')
                    .html('<p>' + coinpays_trans_error + ' ' + coinpays_error + '<br/><small>' + coinpays_error_check_console + '</small></p>');

                console.log(res.responseText);

                resetBtn(button);
            }
        });
    });

</script>