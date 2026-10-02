<?php

declare(strict_types=1);

namespace Adeptix;

/**
 * The Crypto Payments API - unique, exact-amount on-chain deposits for USDT/USDC on BSC, Polygon,
 * or Tron. See https://docs.adeptix.app/crypto-payments
 */
class CryptoPayments
{
    private AdeptixClient $client;

    public function __construct(AdeptixClient $client)
    {
        $this->client = $client;
    }

    /**
     * Reserve a unique deposit amount and get the address to send it to. POST - never
     * auto-retried by the client, since there's no idempotency-key mechanism on the API today.
     *
     * @param array{chain: string, token: string, amount: string, order_ref?: string, customer_email?: string} $params
     * @return array<string, mixed> payment_request_id, chain, token, pay_to_address, amount, expires_at
     */
    public function createPaymentRequest(array $params): array
    {
        return $this->client->request('POST', '/crypto/payment-requests', [
            'chain' => $params['chain'],
            'token' => $params['token'],
            'amount' => $params['amount'],
            'order_ref' => $params['order_ref'] ?? null,
            'customer_email' => $params['customer_email'] ?? null,
        ]);
    }

    /**
     * Check the status of a previously created crypto payment request.
     *
     * @return array<string, mixed> payment_request_id, status, order_ref, requested_amount, quoted_amount, created_at, expires_at
     */
    public function getPaymentRequest(string $paymentRequestId): array
    {
        return $this->client->request('GET', '/crypto/payment-requests/' . rawurlencode($paymentRequestId));
    }
}
