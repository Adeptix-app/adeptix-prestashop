<?php

declare(strict_types=1);

namespace Adeptix;

use Adeptix\Exceptions\AdeptixApiException;
use Adeptix\Exceptions\AdeptixUnexpectedResponseException;

/**
 * Entry point for the Adeptix API. See https://docs.adeptix.app for the full reference.
 *
 * $client = new AdeptixClient('ak_live_...');
 * $request = $client->crypto()->createPaymentRequest(['chain' => 'polygon', 'token' => 'USDC', 'amount' => '49.00']);
 * $payment = $client->payments()->create(['amount' => '49.00', 'currency' => 'USD', 'email' => '...', 'provider' => 'stripe']);
 */
class AdeptixClient
{
    public const DEFAULT_BASE_URL = 'https://api.adeptix.app/v1';
    public const DEFAULT_TIMEOUT_SECONDS = 15;
    public const DEFAULT_MAX_RETRIES = 2;

    private string $apiKey;
    private string $baseUrl;
    private int $timeoutSeconds;
    private int $maxRetries;
    /** @var callable(string, string, array<int, string>, ?string): array{0: int, 1: string} */
    private $httpTransport;

    private ?CryptoPayments $cryptoPayments = null;
    private ?Payments $payments = null;

    /**
     * @param callable(string, string, array<int, string>, ?string): array{0: int, 1: string}|null $httpTransport
     *   Inject a custom transport - mainly for tests. Must return [statusCode, rawResponseBody] and
     *   throw on a network-level failure (no response at all). Defaults to a real cURL request.
     */
    public function __construct(
        string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
        int $maxRetries = self::DEFAULT_MAX_RETRIES,
        ?callable $httpTransport = null
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('AdeptixClient requires an apiKey');
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeoutSeconds = $timeoutSeconds;
        $this->maxRetries = $maxRetries;
        $this->httpTransport = $httpTransport ?? [$this, 'curlTransport'];
    }

    /**
     * @param array<int, string> $headers
     * @return array{0: int, 1: string}
     */
    private function curlTransport(string $method, string $url, array $headers, ?string $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $responseBody = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrno !== 0) {
            throw new \RuntimeException("Adeptix API request failed: {$curlError}");
        }

        return [$statusCode, (string) $responseBody];
    }

    public function crypto(): CryptoPayments
    {
        return $this->cryptoPayments ??= new CryptoPayments($this);
    }

    public function payments(): Payments
    {
        return $this->payments ??= new Payments($this);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, ?array $body = null): array
    {
        $url = $this->baseUrl . $path;
        $isRetryable = $method === 'GET';
        $attempt = 0;

        while (true) {
            $headers = ['Authorization: Bearer ' . $this->apiKey];
            $payload = null;

            if ($body !== null) {
                // Drop null values so an omitted optional field isn't sent as JSON null.
                $payload = json_encode(array_filter($body, static fn ($v) => $v !== null));
                $headers[] = 'Content-Type: application/json';
            }

            try {
                [$statusCode, $responseBody] = ($this->httpTransport)($method, $url, $headers, $payload);
            } catch (\Throwable $e) {
                if ($isRetryable && $attempt < $this->maxRetries) {
                    $attempt++;
                    usleep((int) (2 ** $attempt * 200_000));
                    continue;
                }
                throw $e;
            }

            if ($statusCode >= 200 && $statusCode < 300) {
                $decoded = json_decode((string) $responseBody, true);
                return is_array($decoded) ? $decoded : [];
            }

            if ($isRetryable && $statusCode >= 500 && $attempt < $this->maxRetries) {
                $attempt++;
                usleep((int) (2 ** $attempt * 200_000));
                continue;
            }

            $decoded = json_decode((string) $responseBody, true);
            if (!is_array($decoded) || !isset($decoded['error']) || !is_string($decoded['error'])) {
                throw new AdeptixUnexpectedResponseException($statusCode, (string) $responseBody);
            }

            throw new AdeptixApiException(
                $statusCode,
                $decoded['error'],
                $decoded['message'] ?? null,
                $decoded['details'] ?? null
            );
        }
    }
}
