<?php

use Adeptix\AdeptixClient;
use Adeptix\Exceptions\AdeptixApiException;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Crypto Payments API - creates a unique-amount deposit request, creates the order immediately
 * (Offline scenario - the customer is shown instructions on our own site, never redirected away),
 * and stores the deposit details for hookPaymentReturn() to display.
 */
class AdeptixCryptoModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = $this->context->currency;
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);

        $client = new AdeptixClient((string) Configuration::get('ADEPTIX_API_KEY'));

        try {
            $request = $client->crypto()->createPaymentRequest([
                'chain' => (string) Configuration::get('ADEPTIX_CRYPTO_CHAIN'),
                'token' => (string) Configuration::get('ADEPTIX_CRYPTO_TOKEN'),
                // Store totals are in the shop's own fiat currency, not the crypto asset - a real
                // store needs its own fiat->crypto conversion upstream of this call. Passed through
                // as-is here since that conversion is store-specific, not this module's job.
                'amount' => number_format($total, 2, '.', ''),
                'order_ref' => (string) $cart->id,
                'customer_email' => $customer->email,
            ]);
        } catch (AdeptixApiException $e) {
            Tools::redirect('index.php?controller=order&step=3&adeptix_error=' . urlencode($e->getMessage()));
            return;
        }

        $this->module->validateOrder(
            $cart->id,
            (int) Configuration::get('ADEPTIX_OS_AWAITING'),
            $total,
            $this->module->displayName,
            null,
            [],
            (int) $currency->id,
            false,
            $customer->secure_key
        );

        Db::getInstance()->insert('adeptix_crypto_order', [
            'id_order' => (int) $this->module->currentOrder,
            'payment_request_id' => pSQL($request['payment_request_id']),
            'pay_to_address' => pSQL($request['pay_to_address']),
            'amount' => pSQL($request['amount']),
            'chain' => pSQL($request['chain']),
            'token' => pSQL($request['token']),
            'expires_at' => pSQL($request['expires_at']),
        ]);

        Tools::redirect(
            'index.php?controller=order-confirmation&id_cart=' . $cart->id
            . '&id_module=' . $this->module->id
            . '&id_order=' . $this->module->currentOrder
            . '&key=' . $customer->secure_key
        );
    }
}
