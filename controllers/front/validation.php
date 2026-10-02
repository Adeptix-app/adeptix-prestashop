<?php

use Adeptix\AdeptixClient;
use Adeptix\Exceptions\AdeptixApiException;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Merchant Payments API - creates a hosted checkout and redirects the customer to it. Reached
 * only via the $action URL set in Adeptix::getMerchantPaymentOption(), never rendered directly.
 */
class AdeptixValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Re-check this payment option is still available - the customer's cart/address could
        // have changed since the checkout page was rendered.
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] === $this->module->name) {
                $authorized = true;
                break;
            }
        }
        if (!$authorized) {
            die(Tools::displayError('This payment method is not available.'));
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = $this->context->currency;
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);

        $client = new AdeptixClient((string) Configuration::get('ADEPTIX_API_KEY'));

        try {
            $payment = $client->payments()->create([
                'amount' => number_format($total, 2, '.', ''),
                'currency' => $currency->iso_code,
                'email' => $customer->email,
                'provider' => (string) Configuration::get('ADEPTIX_PROVIDER'),
                // The PrestaShop order doesn't exist yet at this point (it's created a few lines
                // below, once we already have a payment_url to redirect to) - the cart id is the
                // one stable identifier that exists both now and when the webhook arrives later,
                // and Order::getIdByCartId() maps it back to the real order id at that point.
                'order_ref' => (string) $cart->id,
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

        Tools::redirect($payment['payment_url']);
    }
}
