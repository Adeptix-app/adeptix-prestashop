<?php

declare(strict_types=1);

namespace Adeptix;

/**
 * The Merchant Payments API - hosted card/bank/wallet checkout via whichever regulated providers
 * are enabled on your account. See https://docs.adeptix.app/merchant-payments
 */
class Payments
{
    private AdeptixClient $client;

    public function __construct(AdeptixClient $client)
    {
        $this->client = $client;
    }

    /**
     * Open a hosted checkout session with a provider. POST - never auto-retried by the client,
     * since there's no idempotency-key mechanism on the API today.
     *
     * @param array{amount: string, currency: string, email: string, provider: string, order_ref?: string} $params
     * @return array<string, mixed> transaction_id, payment_url
     */
    public function create(array $params): array
    {
        return $this->client->request('POST', '/payment-links', [
            'amount' => $params['amount'],
            'currency' => $params['currency'],
            'email' => $params['email'],
            'provider' => $params['provider'],
            'order_ref' => $params['order_ref'] ?? null,
        ]);
    }

    /**
     * Check the status of a previously created payment.
     *
     * @return array<string, mixed> transaction_id, status, order_ref, currency, amount, provider_id, asset, value_coin, txid_out, created_at, paid_at
     */
    public function get(string $transactionId): array
    {
        return $this->client->request('GET', '/payments/' . rawurlencode($transactionId));
    }
}
