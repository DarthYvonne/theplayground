<?php

namespace App\Payments\MobilePay;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Low-level Vipps MobilePay REST client: access-token handling, ePayment (one
 * time) and Recurring (agreements + charges). Endpoint shapes follow
 * developer.vippsmobilepay.com; verify against the live docs when wiring real
 * credentials. Dormant until MobilePayConfig::isConfigured() is true.
 *
 * Amounts are in minor units (øre) — the same unit we store as *_cents.
 */
class MobilePayClient
{
    private const TOKEN_CACHE_KEY = 'mobilepay.access_token';

    /* -------------------------------------------------------------- auth -- */

    public function accessToken(): string
    {
        return Cache::get(self::TOKEN_CACHE_KEY) ?: $this->fetchAccessToken();
    }

    private function fetchAccessToken(): string
    {
        $res = Http::asJson()->acceptJson()->timeout(15)
            ->withHeaders([
                'client_id' => MobilePayConfig::clientId(),
                'client_secret' => MobilePayConfig::clientSecret(),
                'Ocp-Apim-Subscription-Key' => MobilePayConfig::subscriptionKey(),
            ])
            ->post(MobilePayConfig::baseUrl().'/accesstoken/get');

        $data = $this->ok($res);
        $token = $data['access_token'] ?? null;
        if (! $token) {
            throw new RuntimeException('MobilePay: no access_token in response.');
        }

        $ttl = max(60, (int) ($data['expires_in'] ?? 3600) - 60);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }

    /* ------------------------------------------------------- ePayment v1 -- */

    /**
     * Create a one-time WALLET payment. Returns the decoded body including
     * `redirectUrl` and the `reference` we supplied.
     */
    public function createPayment(int $amount, string $reference, string $returnUrl, string $description): array
    {
        $body = [
            'amount' => ['currency' => MobilePayConfig::currency(), 'value' => $amount],
            'paymentMethod' => ['type' => 'WALLET'],
            'reference' => $reference,
            'returnUrl' => $returnUrl,
            'userFlow' => 'WEB_REDIRECT',
            'paymentDescription' => Str::limit($description, 100, ''),
        ];

        return $this->ok($this->request('POST', '/epayment/v1/payments', $body, idempotent: true));
    }

    public function getPayment(string $reference): array
    {
        return $this->ok($this->request('GET', "/epayment/v1/payments/{$reference}"));
    }

    public function capturePayment(string $reference, int $amount): array
    {
        $body = ['modificationAmount' => ['currency' => MobilePayConfig::currency(), 'value' => $amount]];

        return $this->ok($this->request('POST', "/epayment/v1/payments/{$reference}/capture", $body, idempotent: true));
    }

    public function refundPayment(string $reference, int $amount): array
    {
        $body = ['modificationAmount' => ['currency' => MobilePayConfig::currency(), 'value' => $amount]];

        return $this->ok($this->request('POST', "/epayment/v1/payments/{$reference}/refund", $body, idempotent: true));
    }

    /* ------------------------------------------------------ Recurring v3 -- */

    /**
     * Create a draft agreement (optionally with an initial charge for the first
     * period). Returns the body including `agreementId` and `vippsConfirmationUrl`.
     */
    public function createAgreement(
        int $amount,
        string $productName,
        string $merchantRedirectUrl,
        string $merchantAgreementUrl,
        ?int $initialChargeAmount = null,
        ?string $initialChargeDescription = null,
    ): array {
        $body = [
            'pricing' => ['type' => 'LEGACY', 'amount' => $amount, 'currency' => MobilePayConfig::currency()],
            'interval' => ['unit' => 'MONTH', 'count' => 1],
            'merchantRedirectUrl' => $merchantRedirectUrl,
            'merchantAgreementUrl' => $merchantAgreementUrl,
            'productName' => Str::limit($productName, 45, ''),
        ];
        if ($initialChargeAmount !== null) {
            $body['initialCharge'] = [
                'amount' => $initialChargeAmount,
                'description' => Str::limit($initialChargeDescription ?? $productName, 45, ''),
                'transactionType' => 'DIRECT_CAPTURE',
            ];
        }

        return $this->ok($this->request('POST', '/recurring/v3/agreements', $body, idempotent: true));
    }

    public function getAgreement(string $agreementId): array
    {
        return $this->ok($this->request('GET', "/recurring/v3/agreements/{$agreementId}"));
    }

    public function stopAgreement(string $agreementId): void
    {
        $this->ok($this->request('PATCH', "/recurring/v3/agreements/{$agreementId}", ['status' => 'STOPPED']));
    }

    /**
     * Create one period's charge. $dueDate must be at least one day ahead;
     * MobilePay retries a failed charge for $retryDays days.
     */
    public function createCharge(
        string $agreementId,
        int $amount,
        \DateTimeInterface $dueDate,
        string $description,
        int $retryDays,
    ): array {
        $body = [
            'amount' => $amount,
            'currency' => MobilePayConfig::currency(),
            'description' => Str::limit($description, 45, ''),
            'due' => $dueDate->format('Y-m-d'),
            'retryDays' => $retryDays,
            'transactionType' => 'DIRECT_CAPTURE',
        ];

        return $this->ok($this->request('POST', "/recurring/v3/agreements/{$agreementId}/charges", $body, idempotent: true));
    }

    public function getCharge(string $agreementId, string $chargeId): array
    {
        return $this->ok($this->request('GET', "/recurring/v3/agreements/{$agreementId}/charges/{$chargeId}"));
    }

    public function refundCharge(string $agreementId, string $chargeId, int $amount, string $description): array
    {
        $body = [
            'amount' => $amount,
            'description' => Str::limit($description, 45, ''),
        ];

        return $this->ok($this->request('POST', "/recurring/v3/agreements/{$agreementId}/charges/{$chargeId}/refund", $body, idempotent: true));
    }

    /* ----------------------------------------------------------- plumbing -- */

    private function request(string $method, string $path, array $body = [], bool $idempotent = false): Response
    {
        $headers = [
            'Authorization' => 'Bearer '.$this->accessToken(),
            'Ocp-Apim-Subscription-Key' => MobilePayConfig::subscriptionKey(),
            'Merchant-Serial-Number' => MobilePayConfig::merchantSerialNumber(),
            'Vipps-System-Name' => MobilePayConfig::systemName(),
            'Vipps-System-Version' => MobilePayConfig::systemVersion(),
        ];
        if ($idempotent) {
            // Idempotency-Key lets MobilePay dedupe retried POSTs (create/capture/charge).
            $headers['Idempotency-Key'] = (string) Str::uuid();
        }

        $req = Http::withHeaders($headers)->asJson()->acceptJson()->timeout(20);

        return match (strtoupper($method)) {
            'GET' => $req->get(MobilePayConfig::baseUrl().$path),
            'POST' => $req->post(MobilePayConfig::baseUrl().$path, $body),
            'PATCH' => $req->patch(MobilePayConfig::baseUrl().$path, $body),
            default => throw new RuntimeException("Unsupported method {$method}"),
        };
    }

    private function ok(Response $res): array
    {
        if (! $res->successful()) {
            $msg = $res->json('detail') ?? $res->json('title') ?? ('HTTP '.$res->status());
            throw new RuntimeException('MobilePay: '.$msg);
        }

        return $res->json() ?? [];
    }
}
