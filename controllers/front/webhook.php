<?php

use Adeptix\Webhooks;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Server-to-server webhook receiver for both event types. Registered as this module's fixed
 * webhook URL (Adeptix::getWebhookUrl()) in the Adeptix dashboard's Settings page - see
 * https://docs.adeptix.app/webhooks
 */
class AdeptixWebhookModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess()
    {
        $rawBody = (string) file_get_contents('php://input');
        $signature = isset($_SERVER['HTTP_X_ADEPTIX_SIGNATURE']) ? $_SERVER['HTTP_X_ADEPTIX_SIGNATURE'] : null;
        $secret = (string) Configuration::get('ADEPTIX_WEBHOOK_SECRET');

        if (!Webhooks::verifySignature($rawBody, $signature, $secret)) {
            http_response_code(401);
            exit;
        }

        $event = json_decode($rawBody, true);
        if (!is_array($event) || !isset($event['order_ref'])) {
            http_response_code(200); // acknowledge, nothing usable to act on
            exit;
        }

        $eventName = $event['event'] ?? null;
        if ($eventName !== 'payment.paid' && $eventName !== 'crypto_payment.matched') {
            http_response_code(200);
            exit;
        }

        $idOrder = (int) Order::getIdByCartId((int) $event['order_ref']);
        if ($idOrder === 0) {
            http_response_code(200); // unknown cart/order - not ours, or already gone; ack anyway
            exit;
        }

        $order = new Order($idOrder);
        if (Validate::isLoadedObject($order) && !$order->hasBeenPaid()) {
            $order->addOrderHistory((int) Configuration::get('PS_OS_PAYMENT'));
        }

        http_response_code(200);
        exit;
    }
}
