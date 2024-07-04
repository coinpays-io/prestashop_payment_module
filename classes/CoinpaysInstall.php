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

class CoinpaysInstall
{
    # Create Order Table
    public function createOrderTable()
    {
        if (!Db::getInstance()->Execute('
        CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'coinpays_transaction` (
            `id_coinpays` int(11) AUTO_INCREMENT NOT NULL,
            `id_oid_cart` int(11) NOT NULL,
            `id_dup_cart` int(11) NOT NULL,
            `order_token` varchar(64) NOT NULL,
            `merchant_oid` varchar(64) NOT NULL,
            `total` decimal(20,6) NOT NULL,
            `total_paid` decimal(20,6) NULL,
            `status` varchar(64) NULL,
            `status_message` text NULL,
            `is_complete` tinyint(1) NOT NULL,
            `is_order` tinyint(1) NOT NULL,
            `is_notify_order` tinyint(1) NOT NULL,
            `is_refunded` tinyint(1) NOT NULL,
            `refund_status` varchar(64) NULL,
            `refund_amount` decimal(20,6) NULL,
            `date_added` datetime NOT NULL,
            `date_updated` datetime NULL,
            PRIMARY KEY (`id_coinpays`),
            KEY (`id_oid_cart`, `order_token`, `merchant_oid`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8')) {
            return false;
        }
    }

    # Create Order State
    public function createOrderState()
    {
        $conf_pending = $this->getOrderState('COINPAYS_PENDING');

        if ($conf_pending) {
            Configuration::updateValue('COINPAYS_PENDING', (int)$conf_pending['id_order_state']);
        } else {
            $order_state_pending = new OrderState();

            // Create Order State : PENDING
            $order_state_pending->name = array();
            $order_state_pending->color = '#34219E';
            $order_state_pending->module_name = 'COINPAYS_PENDING';
            $order_state_pending->unremovable = 1;

            foreach (Language::getLanguages() as $language) {
                if (Tools::strtolower($language['iso_code']) == 'tr') {
                    $order_state_pending->name[$language['id_lang']] = 'CoinPays Ödemesi Bekleniyor';
                } else {
                    $order_state_pending->name[$language['id_lang']] = 'Awaiting CoinPays Payment';
                }
            }

            if ($order_state_pending->add()) {
                Configuration::updateValue('COINPAYS_PENDING', (int)$order_state_pending->id);
            }
        }
    }

    # Update Configurations
    public function updateConfigurations()
    {
        Configuration::updateValue('COINPAYS_MERCHANT_ID', '');
        Configuration::updateValue('COINPAYS_MERCHANT_KEY', '');
        Configuration::updateValue('COINPAYS_MERCHANT_SALT', '');
        Configuration::updateValue('COINPAYS_LOGO', '1');

        $languages = Language::getLanguages(false);
        foreach ($languages as $language) {
            Configuration::updateValue('COINPAYS_METHOD_TITLE_' . $language['id_lang'], 'CoinPays Crypto Payment');
        }

        Configuration::updateValue('COINPAYS_COMPLETED', '2');
        Configuration::updateValue('COINPAYS_CANCELED', '8');
        Configuration::updateValue('COINPAYS_INVOICE', '1');
        Configuration::updateValue('COINPAYS_INSTALLMENT', '0');
        Configuration::updateValue('COINPAYS_REFUND', '0');
    }

    # Delete Configurations
    public function deleteConfigurations()
    {
        // Configurations
        Configuration::deleteByName('COINPAYS_MERCHANT_ID');
        Configuration::deleteByName('COINPAYS_MERCHANT_KEY');
        Configuration::deleteByName('COINPAYS_MERCHANT_SALT');
        Configuration::deleteByName('COINPAYS_LOGO');
        Configuration::deleteByName('COINPAYS_METHOD_TITLE');
        Configuration::deleteByName('COINPAYS_COMPLETED');
        Configuration::deleteByName('COINPAYS_CANCELED');
        Configuration::deleteByName('COINPAYS_PENDING');
        Configuration::deleteByName('COINPAYS_REFUND');
        Configuration::deleteByName('COINPAYS_INVOICE');
        Configuration::deleteByName('COINPAYS_INSTALLMENT');
        Configuration::deleteByName('COINPAYS_CI');

        $languages = Language::getLanguages(false);

        foreach ($languages as $language) {
            Configuration::deleteByName('COINPAYS_METHOD_TITLE_' . $language['id_lang']);
        }

        // Drop Tables
        Db::getInstance()->execute("DROP TABLE if exists " . _DB_PREFIX_ . "coinpays_transaction");
    }

    # Get Order State
    public function getOrderState($order_state)
    {
        return Db::getInstance()->getRow("SELECT * FROM `" . _DB_PREFIX_ . "order_state` WHERE `module_name` = '" . pSQL($order_state) . "'");
    }
}
