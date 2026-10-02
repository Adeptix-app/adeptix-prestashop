<?php
/**
 * Adeptix payment module for PrestaShop.
 *
 * Two payment options registered from one module: a "Merchant Payments" hosted checkout
 * (External scenario per PrestaShop's own payment-module docs - https://devdocs.prestashop-project.org/9/modules/payment/)
 * and a "Crypto Payments" direct on-chain deposit (Offline scenario, same doc).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class Adeptix extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'adeptix';
        $this->tab = 'payments_gateways';
        $this->version = '0.1.0';
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->author = 'Adeptix';
        $this->controllers = ['validation', 'crypto', 'webhook'];
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Adeptix');
        $this->description = $this->l('Accept card/bank/wallet checkout and direct on-chain USDT/USDC payments via Adeptix.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the Adeptix payment module?');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
        if (!Configuration::get('ADEPTIX_API_KEY')) {
            $this->warning = $this->l('Set your Adeptix API key in the module\'s configuration page.');
        }
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && Configuration::updateValue('ADEPTIX_API_KEY', '')
            && Configuration::updateValue('ADEPTIX_WEBHOOK_SECRET', '')
            && Configuration::updateValue('ADEPTIX_PROVIDER', 'stripe')
            && Configuration::updateValue('ADEPTIX_CRYPTO_CHAIN', 'polygon')
            && Configuration::updateValue('ADEPTIX_CRYPTO_TOKEN', 'USDC')
            && $this->installDb()
            && $this->installOrderState();
    }

    /**
     * A dedicated "Awaiting Adeptix payment" state, set on an order the moment the customer is
     * redirected to Adeptix (before any confirmation) - reusing an unrelated built-in state like
     * "Awaiting cheque payment" would be confusing in the order list/customer emails.
     */
    protected function installOrderState(): bool
    {
        if (Configuration::get('ADEPTIX_OS_AWAITING')) {
            return true;
        }

        $orderState = new OrderState();
        $orderState->name = [];
        foreach (Language::getLanguages(false) as $lang) {
            $orderState->name[$lang['id_lang']] = 'Awaiting Adeptix payment';
        }
        $orderState->send_email = false;
        $orderState->color = '#4169E1';
        $orderState->hidden = false;
        $orderState->delivery = false;
        $orderState->logable = false;
        $orderState->invoice = false;
        $orderState->paid = false;

        if (!$orderState->add()) {
            return false;
        }

        return Configuration::updateValue('ADEPTIX_OS_AWAITING', (int) $orderState->id);
    }

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName('ADEPTIX_API_KEY')
            && Configuration::deleteByName('ADEPTIX_WEBHOOK_SECRET')
            && Configuration::deleteByName('ADEPTIX_PROVIDER')
            && Configuration::deleteByName('ADEPTIX_CRYPTO_CHAIN')
            && Configuration::deleteByName('ADEPTIX_CRYPTO_TOKEN')
            && Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'adeptix_crypto_order`')
            && $this->uninstallOrderState();
    }

    protected function uninstallOrderState(): bool
    {
        $id = (int) Configuration::get('ADEPTIX_OS_AWAITING');
        if ($id) {
            $orderState = new OrderState($id);
            if (Validate::isLoadedObject($orderState)) {
                $orderState->delete();
            }
        }
        return Configuration::deleteByName('ADEPTIX_OS_AWAITING');
    }

    /**
     * One row per order paid through the crypto option - the deposit address/amount/chain/token
     * shown on the order-confirmation page (hookPaymentReturn) and looked up by the webhook
     * controller to mark the matching order paid.
     */
    protected function installDb(): bool
    {
        return Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'adeptix_crypto_order` (
                `id_order` INT UNSIGNED NOT NULL PRIMARY KEY,
                `payment_request_id` VARCHAR(64) NOT NULL,
                `pay_to_address` VARCHAR(128) NOT NULL,
                `amount` VARCHAR(32) NOT NULL,
                `chain` VARCHAR(16) NOT NULL,
                `token` VARCHAR(8) NOT NULL,
                `expires_at` VARCHAR(40) NOT NULL,
                UNIQUE KEY `payment_request_id` (`payment_request_id`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * The webhook URL to register in the Adeptix dashboard's Settings page - a fixed, public URL
     * that doesn't depend on any specific cart/order (unlike the validation/crypto controllers,
     * which are only ever reached via a redirect from checkout).
     */
    public function getWebhookUrl(): string
    {
        return $this->context->link->getModuleLink($this->name, 'webhook', [], true);
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitAdeptixConfig')) {
            Configuration::updateValue('ADEPTIX_API_KEY', trim((string) Tools::getValue('ADEPTIX_API_KEY')));
            Configuration::updateValue('ADEPTIX_WEBHOOK_SECRET', trim((string) Tools::getValue('ADEPTIX_WEBHOOK_SECRET')));
            Configuration::updateValue('ADEPTIX_PROVIDER', trim((string) Tools::getValue('ADEPTIX_PROVIDER')));
            Configuration::updateValue('ADEPTIX_CRYPTO_CHAIN', trim((string) Tools::getValue('ADEPTIX_CRYPTO_CHAIN')));
            Configuration::updateValue('ADEPTIX_CRYPTO_TOKEN', trim((string) Tools::getValue('ADEPTIX_CRYPTO_TOKEN')));
            $output .= $this->displayConfirmation($this->l('Settings updated.'));
        }

        $output .= '<div class="alert alert-info">' . sprintf(
            $this->l('Register this URL as your webhook URL in the Adeptix dashboard\'s Settings page: %s'),
            '<code>' . htmlspecialchars($this->getWebhookUrl()) . '</code>'
        ) . '</div>';

        return $output . $this->renderForm();
    }

    protected function renderForm(): string
    {
        $fieldsForm = [
            'form' => [
                'legend' => ['title' => $this->l('Adeptix settings')],
                'input' => [
                    ['type' => 'password', 'label' => $this->l('API key'), 'name' => 'ADEPTIX_API_KEY', 'desc' => $this->l('From your Adeptix dashboard\'s API Keys page.')],
                    ['type' => 'password', 'label' => $this->l('Webhook secret'), 'name' => 'ADEPTIX_WEBHOOK_SECRET', 'desc' => $this->l('From your Adeptix dashboard\'s Settings page.')],
                    ['type' => 'text', 'label' => $this->l('Provider ID'), 'name' => 'ADEPTIX_PROVIDER', 'desc' => $this->l('An enabled provider id, e.g. "stripe".')],
                    ['type' => 'select', 'label' => $this->l('Crypto chain'), 'name' => 'ADEPTIX_CRYPTO_CHAIN', 'options' => [
                        'query' => [['id' => 'bsc', 'name' => 'BSC'], ['id' => 'polygon', 'name' => 'Polygon'], ['id' => 'tron', 'name' => 'Tron']],
                        'id' => 'id', 'name' => 'name',
                    ]],
                    ['type' => 'select', 'label' => $this->l('Crypto token'), 'name' => 'ADEPTIX_CRYPTO_TOKEN', 'options' => [
                        'query' => [['id' => 'USDT', 'name' => 'USDT'], ['id' => 'USDC', 'name' => 'USDC']],
                        'id' => 'id', 'name' => 'name',
                    ]],
                ],
                'submit' => ['title' => $this->l('Save')],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitAdeptixConfig';
        $helper->fields_value = [
            'ADEPTIX_API_KEY' => Configuration::get('ADEPTIX_API_KEY'),
            'ADEPTIX_WEBHOOK_SECRET' => Configuration::get('ADEPTIX_WEBHOOK_SECRET'),
            'ADEPTIX_PROVIDER' => Configuration::get('ADEPTIX_PROVIDER'),
            'ADEPTIX_CRYPTO_CHAIN' => Configuration::get('ADEPTIX_CRYPTO_CHAIN'),
            'ADEPTIX_CRYPTO_TOKEN' => Configuration::get('ADEPTIX_CRYPTO_TOKEN'),
        ];

        return $helper->generateForm([$fieldsForm]);
    }

    /**
     * @return PaymentOption[]|void
     */
    public function hookPaymentOptions(array $params)
    {
        if (!$this->active || !Configuration::get('ADEPTIX_API_KEY')) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $merchantOption = new PaymentOption();
        $merchantOption->setModuleName($this->name)
            ->setCallToActionText($this->l('Card / Bank / Wallet (Adeptix)'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', [], true))
            ->setAdditionalInformation($this->context->smarty->fetch('module:adeptix/views/templates/front/payment_infos.tpl'));

        $cryptoOption = new PaymentOption();
        $cryptoOption->setModuleName($this->name)
            ->setCallToActionText($this->l('Crypto (USDT/USDC via Adeptix)'))
            ->setAction($this->context->link->getModuleLink($this->name, 'crypto', [], true))
            ->setAdditionalInformation($this->context->smarty->fetch('module:adeptix/views/templates/front/crypto_infos.tpl'));

        return [$merchantOption, $cryptoOption];
    }

    public function checkCurrency($cart): bool
    {
        $currencyOrder = new Currency($cart->id_currency);
        $currenciesModule = $this->getCurrency($cart->id_currency);

        if (is_array($currenciesModule)) {
            foreach ($currenciesModule as $currencyModule) {
                if ($currencyOrder->id == $currencyModule['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function hookPaymentReturn(array $params)
    {
        if (!$this->active) {
            return;
        }

        /** @var Order $order */
        $order = $params['order'] ?? null;
        if (!$order instanceof Order) {
            return;
        }

        $this->context->smarty->assign('order', $order);

        // If this order was paid via the crypto option, show the deposit instructions instead of
        // the generic "thank you" text - looked up by order id, populated by controllers/front/crypto.php.
        $row = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'adeptix_crypto_order` WHERE `id_order` = ' . (int) $order->id
        );
        if ($row) {
            $this->context->smarty->assign('adeptix_crypto', $row);
            return $this->context->smarty->fetch('module:adeptix/views/templates/front/crypto_return.tpl');
        }

        return $this->context->smarty->fetch('module:adeptix/views/templates/front/payment_return.tpl');
    }
}
