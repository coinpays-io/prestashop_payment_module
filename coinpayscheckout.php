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

if (!defined('_PS_VERSION_')) {
    exit;
}
require_once(dirname(__FILE__) . '/classes/CoinpaysInstall.php');

class CoinpaysCheckout extends PaymentModule
{
    private $contentHtml;
    private $postErrors = array();

    public function __construct()
    {
        $this->name = 'coinpayscheckout';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'CoinPays Crypto Payment Gateway';
        $this->need_instance = 1;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('CoinPays iFrame API');
        $this->description = $this->l('The infrastructure required to receive payments through PrestaShop with your CoinPays membership.');

        $this->confirmUninstall = $this->l('Do you want to uninstall CoinPays module?');

        $this->limited_currencies = array('TRY', 'TL', 'EUR', 'USD', 'GBP', 'RUB');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->postErrors[] = $this->l('You have to enable the cURL extension on your server to install this module.');
            return false;
        }

        if (!parent::install() ||
            !$this->registerHook('header') ||
            !$this->registerHook('payment') ||
            !$this->registerHook('displayAdminOrder') ||
            !$this->registerHook('paymentOptions')) {
            return false;
        }

        $install = new CoinpaysInstall();
        $install->createOrderTable();
        $install->createOrderState();
        $install->updateConfigurations();

        $source = dirname(__FILE__) . '/views/img/os.gif';
        $destination_pending = dirname(__FILE__) . '/../../img/os/' . Configuration::get('COINPAYS_PENDING') . '.gif';
        copy($source, $destination_pending);

        return true;
    }

    public function uninstall()
    {
        $uninstall = new CoinpaysInstall();
        $uninstall->deleteConfigurations();

        return parent::uninstall();
    }

    protected function installmentOptions($categoryBased = false)
    {
        $installments = array();

        for ($i = 0; $i <= 12; $i++) {
            if ($i == 0) {
                $installments[] = array('id_option' => 0, 'name' => $this->l('All Installment Options'));
            } elseif ($i == 1) {
                $installments[] = array('id_option' => 1, 'name' => $this->l('One Shot (No Installment)'));
            } else {
                $installments[] = array('id_option' => $i, 'name' => $this->l('Up to') . " " . $i . " " . $this->l('Installment'));
            }
        }

        if ($categoryBased) {
            array_push($installments, array('id_option' => 13, 'name' => $this->l('CATEGORY BASED')));
        }

        return $installments;
    }

    protected function getConfigFormValues()
    {
        $all_inputs = array();
        $category_based = array();
        $languages = Language::getLanguages(false);

        $form_inputs = array(
            'coinpays_merchant_id' => Tools::getValue('coinpays_merchant_id', Configuration::get('COINPAYS_MERCHANT_ID')),
            'coinpays_merchant_key' => Tools::getValue('coinpays_merchant_key', Configuration::get('COINPAYS_MERCHANT_KEY')),
            'coinpays_merchant_salt' => Tools::getValue('coinpays_merchant_salt', Configuration::get('COINPAYS_MERCHANT_SALT')),
            'coinpays_logo' => Tools::getValue('coinpays_logo', Configuration::get('COINPAYS_LOGO')),
            'coinpays_completed' => Tools::getValue('coinpays_completed', Configuration::get('COINPAYS_COMPLETED')),
            'coinpays_canceled' => Tools::getValue('coinpays_canceled', Configuration::get('COINPAYS_CANCELED')),
            'coinpays_invoice' => Tools::getValue('coinpays_invoice', Configuration::get('COINPAYS_INVOICE')),
            'coinpays_installment' => Tools::getValue('coinpays_installment', Configuration::get('COINPAYS_INSTALLMENT')),
            'coinpays_refund' => Tools::getValue('coinpays_refund', Configuration::get('COINPAYS_REFUND'))
        );

        foreach ($languages as $language) {
            $form_inputs['coinpays_method_title'][$language['id_lang']] = Tools::getValue('coinpays_method_title', Configuration::get('COINPAYS_METHOD_TITLE_' . $language['id_lang']));
        }

        if (Configuration::get('COINPAYS_INSTALLMENT') == 13) {
            $array_CI = unserialize(Configuration::get('COINPAYS_CI'));

            foreach ($array_CI as $key => $val) {
                $category_based['CI[' . $key . ']'] = $val;
            }

            $all_inputs = array_merge($form_inputs, $category_based);
        } else {
            $all_inputs = $form_inputs;
        }

        return $all_inputs;
    }

    protected function displayFormInfo()
    {
        $this->context->smarty->assign(
            [
                'img' => Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/logo.png'),
                'coinpays_callback_url' => "https://" . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . "module/coinpayscheckout/notification"
            ]
        );
        return $this->display(__FILE__, 'views/templates/admin/info.tpl');
    }

    protected function displayForm()
    {
        // Order States
        $orderStates = OrderState::getOrderStates((int)$this->context->language->id);

        // Status Options
        $status_options = array(
            array('id_option' => 0, 'name' => $this->l('Disabled')),
            array('id_option' => 1, 'name' => $this->l('Enabled'))
        );

        // Form Inputs
        $form_inputs = array(
            array(
                'type' => 'text',
                'label' => $this->l('Merchant Id'),
                'name' => 'coinpays_merchant_id',
                'class' => 'md',
                'required' => true
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Merchant Key'),
                'name' => 'coinpays_merchant_key',
                'class' => 'md',
                'required' => true
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Merchant Salt'),
                'name' => 'coinpays_merchant_salt',
                'class' => 'md',
                'required' => true
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Logo Show/Hide'),
                'name' => 'coinpays_logo',
                'options' => array(
                    'query' => $status_options,
                    'id' => 'id_option',
                    'name' => 'name',
                )
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Payment Method Title'),
                'name' => 'coinpays_method_title',
                'class' => 'md',
                'lang' => true
            ),
            array(
                'type' => 'select',
                'label' => $this->l('When Payment Successful'),
                'name' => 'coinpays_completed',
                'options' => array(
                    'query' => $orderStates,
                    'id' => 'id_order_state',
                    'name' => 'name'
                )
            ),
            array(
                'type' => 'select',
                'label' => $this->l('When Payment Failed'),
                'name' => 'coinpays_canceled',
                'options' => array(
                    'query' => $orderStates,
                    'id' => 'id_order_state',
                    'name' => 'name'
                )
            ),
        );
        $inputs = $form_inputs;

        // Store Information Form
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('SETTINGS'),
                    'icon' => 'icon-pencil'
                ),
                'input' => $inputs,
                'submit' => array(
                    'title' => $this->l('Save'),
                )
            )
        );

        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitCoinpaysModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    protected function postValidation()
    {
        if (Tools::isSubmit('submitCoinpaysModule')) {
            $merchant_id = $this->sanitizeInput(Tools::getValue('coinpays_merchant_id'));
            $merchant_key = $this->sanitizeInput(Tools::getValue('coinpays_merchant_key'));
            $merchant_salt = $this->sanitizeInput(Tools::getValue('coinpays_merchant_salt'));

            // Check Merchant ID != null
            if ($merchant_id == null) {
                $this->postErrors[] = $this->l('Merchant No is required');
            } else if (!is_numeric($merchant_id)) {
                $this->postErrors[] = $this->l('Merchant No must be numeric');
            }
            // Check Merchant Key != null
            if ($merchant_key == null) {
                $this->postErrors[] = $this->l('Merchant Key required');
            } else if (Tools::strlen($merchant_key) != 16) {
                $this->postErrors[] = $this->l('Merchant Key must be 16 char');
            }
            // Check Merchant Salt != null
            if ($merchant_salt == null) {
                $this->postErrors[] = $this->l('Merchant Salt is required');
            } else if (Tools::strlen($merchant_salt) != 16) {
                $this->postErrors[] = $this->l('Merchant Salt must be 16 char');
            }
        }
    }

    protected function postProcess()
    {
        if (Tools::isSubmit('submitCoinpaysModule')) {
            Configuration::updateValue('COINPAYS_MERCHANT_ID', $this->sanitizeInput(Tools::getValue('coinpays_merchant_id')));
            Configuration::updateValue('COINPAYS_MERCHANT_KEY', $this->sanitizeInput(Tools::getValue('coinpays_merchant_key')));
            Configuration::updateValue('COINPAYS_MERCHANT_SALT', $this->sanitizeInput(Tools::getValue('coinpays_merchant_salt')));
            Configuration::updateValue('COINPAYS_LOGO', Tools::getValue('coinpays_logo'));

            $languages = Language::getLanguages(false);
            foreach ($languages as $language) {
                Configuration::updateValue('COINPAYS_METHOD_TITLE_' . $language['id_lang'], Tools::getValue('coinpays_method_title_' . $language['id_lang']));
            }

            Configuration::updateValue('COINPAYS_COMPLETED', Tools::getValue('coinpays_completed'));
            Configuration::updateValue('COINPAYS_CANCELED', Tools::getValue('coinpays_canceled'));
            Configuration::updateValue('COINPAYS_INVOICE', Tools::getValue('coinpays_invoice'));
            Configuration::updateValue('COINPAYS_INSTALLMENT', Tools::getValue('coinpays_installment'));
            Configuration::updateValue('COINPAYS_REFUND', Tools::getValue('coinpays_refund'));

            if (Tools::getValue('coinpays_installment') == 13) {
                Configuration::updateValue('COINPAYS_CI', serialize(Tools::getValue('CI')));
            }

            $this->contentHtml .= $this->displayConfirmation($this->l('Settings updated'));
        }
    }

    protected function sanitizeInput($key)
    {
        return Tools::safeOutput(str_replace(' ', '', preg_replace('/^\p{Z}+|\p{Z}+$/u', ' ', $key)), false);
    }

    public function getContent()
    {
        if (Tools::isSubmit('submitCoinpaysModule')) {
            $this->postValidation();

            if (!count($this->postErrors)) {
                $this->postProcess();
            } else {
                foreach ($this->postErrors as $err) {
                    $this->contentHtml .= $this->displayError($err);
                }
            }
        }

        $this->contentHtml .= $this->displayFormInfo();
        $this->contentHtml .= $this->displayForm();

        return $this->contentHtml;
    }

    # Category Based Options
    public function categoryParser($start_id = 2)
    {
        $cats = Db::getInstance()->ExecuteS('SELECT c.id_category AS "id", c.id_parent AS "parent_id", cl.name AS "name" FROM ' . _DB_PREFIX_ . 'category c LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl ON (c.id_category = cl.id_category) ORDER BY cl.name ASC');
        $cat_tree = array();
        foreach ($cats as $key => $item) {
            if ($item['parent_id'] == $start_id) {
                $cat_tree[$item['id']] = array('id' => $item['id'], 'name' => $item['name']);
                $this->parentCategoryParser($cats, $cat_tree[$item['id']]);
            }
        }
        return $cat_tree;
    }

    public function parentCategoryParser(&$cats = array(), &$cat_tree = array())
    {
        foreach ($cats as $key => $item) {
            if ($item['parent_id'] == $cat_tree['id']) {
                $cat_tree['parent'][$item['id']] = array('id' => $item['id'], 'name' => $item['name']);
                $this->parentCategoryParser($cats, $cat_tree['parent'][$item['id']]);
            }
        }
    }

    public function categoryParserClear($tree, $level = 0, $arr = array(), &$finish_him = array())
    {
        foreach ($tree as $id => $item) {
            if ($level == 0) {
                unset($arr);
                $arr = array();
                $arr[] = $item['name'];
            } elseif ($level == 1 or $level == 2) {
                if (count($arr) == ($level + 1)) {
                    $deleted = array_pop($arr);
                }
                $arr[] = $item['name'];
            }
            if ($level < 3) {
                $nav = null;
                foreach ($arr as $key => $val) {
                    $nav .= $val . ($level != 0 ? ' > ' : null);
                }
                $finish_him[$item['id']] = rtrim($nav, ' > ') . '<br>';
                if (!empty($item['parent'])) {
                    $this->categoryParserClear($item['parent'], $level + 1, $arr, $finish_him);
                }
            }
        }
    }

    # Hooks
    public function hookHeader()
    {
        if (Configuration::get('COINPAYS_LOGO')) {
            $this->context->controller->addCSS($this->_path . 'views/css/coinpays.css');
        }
    }

    public function hookPayment($params)
    {
        $currency_id = $params['cart']->id_currency;
        $currency = new Currency((int)$currency_id);

        if (in_array($currency->iso_code, $this->limited_currencies) == false) {
            return false;
        }

        $this->smarty->assign('ps', array(
            'module_dir' => $this->_path,
            'method_title' => $this->getActionText()
        ));

        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }

    /**
     * Return payment options available for PS 1.7+
     *
     * @param array Hook parameters
     *
     * @return array|null
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $option = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setCallToActionText($this->getActionText())
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true));

        if (Configuration::get('COINPAYS_LOGO')) {
            $option->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/logo.png'));
        }

        return [
            $option
        ];
    }

    public function hookDisplayAdminOrder($params)
    {
        $order = new Order($params['id_order']);
        $currency = new Currency($order->id_currency);

        if ($order->module == $this->name) {
            $this->smarty->assign('pt', array(
                'icon_coinpays' => Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/os.gif'),
                'icon_loader' => Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/loader.gif'),
                'path' => $this->context->link->getModuleLink('coinpayscheckout', 'refund'),
                'id_order' => $order->id,
                'currency_icon' => $currency->sign
            ));

            return $this->display(__FILE__, 'views/templates/hook/order.tpl');
        }
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getActionText()
    {
        $lang_id = $this->context->language->id;
        $actionText = Configuration::get('COINPAYS_METHOD_TITLE_' . $lang_id);

        if ($actionText == null || $actionText == "") {
            $actionText = 'Kredi / Banka Kartı';
        }

        return $actionText;
    }
}
