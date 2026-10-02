# adeptix/adeptix-php

Official PHP SDK for the [Adeptix API](https://docs.adeptix.app) — direct on-chain crypto
payments and hosted card/bank/wallet checkouts, from a single client.

Full API reference: **https://docs.adeptix.app**

## Install

```bash
composer require adeptix/adeptix-php
```

> **Not on Packagist yet.** Until it is, add this repository to your project's `composer.json`
> and require the `dev-main` branch:
>
> ```json
> "repositories": [{ "type": "vcs", "url": "https://github.com/Adeptix-app/adeptix-php" }]
> ```
>
> ```bash
> composer require adeptix/adeptix-php:dev-main
> ```

Requires PHP 8.0+ with the `curl` and `json` extensions (bundled with virtually every PHP
install). This is also the foundation the official WooCommerce/WHMCS/PrestaShop/OpenCart plugins
build on — install it directly only if you're integrating custom PHP code.

## Quickstart

```php
use Adeptix\AdeptixClient;

$client = new AdeptixClient(getenv('ADEPTIX_API_KEY'));
```

### Crypto Payments API

```php
$request = $client->crypto()->createPaymentRequest([
    'chain' => 'polygon',
    'token' => 'USDC',
    'amount' => '49.00',
    'order_ref' => 'order_10231',
]);

// Show $request['amount'] and $request['pay_to_address'] to the customer.
// $request['amount'] may differ slightly from "49.00" - see the docs' note on how deposits are
// matched by exact amount, not by a per-request address.

$status = $client->crypto()->getPaymentRequest($request['payment_request_id']);
// $status['status']: "pending" | "matched" | "expired"
```

### Merchant Payments API

```php
$payment = $client->payments()->create([
    'amount' => '49.00',
    'currency' => 'USD',
    'email' => 'customer@example.com',
    'provider' => 'stripe',
    'order_ref' => 'order_10231',
]);

// Redirect the customer's browser to $payment['payment_url'].

$status = $client->payments()->get($payment['transaction_id']);
// $status['status']: "pending" | "paid" | "failed" | "expired"
```

## Webhooks

Prefer webhooks over polling — see **https://docs.adeptix.app/webhooks** for how to configure a
webhook URL and secret from your dashboard.

```php
use Adeptix\Webhooks;

// IMPORTANT: verification needs the raw, unparsed body.
$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_ADEPTIX_SIGNATURE'] ?? null;

try {
    $event = Webhooks::parseEvent($rawBody, $signature, getenv('ADEPTIX_WEBHOOK_SECRET'));
} catch (\RuntimeException $e) {
    http_response_code(401);
    exit;
}

if ($event['event'] === 'payment.paid') {
    // mark your order paid - $event['order_ref'] / $event['transaction_id']
} elseif ($event['event'] === 'crypto_payment.matched') {
    // mark your order paid - $event['order_ref'] / $event['payment_request_id']
}

http_response_code(200);
```

Handlers should be idempotent — the same event can be delivered more than once.

## Error handling

Every non-2xx response throws `Adeptix\Exceptions\AdeptixApiException`. Always check
`getErrorCode()`, not `getMessage()` (which isn't guaranteed to be present or stable):

```php
use Adeptix\Exceptions\AdeptixApiException;

try {
    $client->payments()->create(['amount' => '49.00', 'currency' => 'USD', 'email' => 'a@b.com', 'provider' => 'stripe']);
} catch (AdeptixApiException $e) {
    error_log("{$e->getStatusCode()} {$e->getErrorCode()}");
}
```

See **https://docs.adeptix.app/errors** for the full list of error codes.

## Retries

`GET` requests (status checks) are automatically retried on a network error or a 5xx response,
with exponential backoff (2 retries by default). `POST` requests are **never** auto-retried —
there's no idempotency-key mechanism on the API today, so a retried `POST` could create a second
payment request or checkout session. Configure with the constructor's 4th argument:

```php
$client = new AdeptixClient(
    apiKey: getenv('ADEPTIX_API_KEY'),
    maxRetries: 3,
    timeoutSeconds: 20
);
```

## Testing this SDK itself

```bash
composer install
composer test
```

## License

MIT
