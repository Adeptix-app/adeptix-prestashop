<?php

declare(strict_types=1);

namespace Adeptix;

/**
 * Verifies and parses Adeptix webhook deliveries. See https://docs.adeptix.app/webhooks
 */
class Webhooks
{
    /**
     * Verifies the `x-adeptix-signature` header against the raw (unparsed) request body using
     * your webhook secret. Always call this BEFORE json_decode-ing the body - signing covers the
     * exact bytes received, not whatever your JSON parser normalizes them to.
     */
    public static function verifySignature(string $rawBody, ?string $signatureHeader, string $webhookSecret): bool
    {
        if ($signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $provided = preg_replace('/^sha256=/', '', $signatureHeader);
        $expected = hash_hmac('sha256', $rawBody, $webhookSecret);

        return hash_equals($expected, (string) $provided);
    }

    /**
     * Verifies the signature and decodes the body in one step. Throws if the signature doesn't
     * match or the body isn't valid JSON - never returns an unverified event.
     *
     * @return array<string, mixed>
     */
    public static function parseEvent(string $rawBody, ?string $signatureHeader, string $webhookSecret): array
    {
        if (!self::verifySignature($rawBody, $signatureHeader, $webhookSecret)) {
            throw new \RuntimeException('Adeptix webhook signature verification failed');
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Adeptix webhook payload was not valid JSON');
        }

        return $decoded;
    }
}
