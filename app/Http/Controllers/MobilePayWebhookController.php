<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Payments\EnrollmentNotifier;
use App\Payments\MobilePay\MobilePayConfig;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Inbound Vipps MobilePay webhooks for agreement and charge lifecycle events.
 * Event names/payload shapes follow the MobilePay Webhooks API; verify against
 * the live docs when wiring real credentials.
 */
class MobilePayWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        if (! $this->verify($request, $payload)) {
            return response()->json(['error' => 'invalid signature'], 400);
        }

        $event = json_decode($payload, true);
        if (! is_array($event)) {
            return response()->json(['error' => 'bad payload'], 400);
        }

        // Dedupe on a stable id (header message id, payload id, or a body hash).
        $eventId = $request->header('x-ms-message-id')
            ?? ($event['id'] ?? ($event['idempotencyKey'] ?? hash('sha256', $payload)));
        try {
            DB::table('webhook_events')->insert([
                'provider' => 'mobilepay',
                'event_id' => $eventId,
                'type' => $event['name'] ?? ($event['eventType'] ?? null),
                'created_at' => now(),
            ]);
        } catch (QueryException) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        $name = strtolower((string) ($event['name'] ?? $event['eventType'] ?? ''));

        match (true) {
            str_contains($name, 'agreement') => $this->handleAgreement($event),
            str_contains($name, 'charge') => $this->handleCharge($event),
            default => null,
        };

        return response()->json(['ok' => true]);
    }

    /** Validate the HMAC signature when a webhook secret is configured. */
    private function verify(Request $request, string $payload): bool
    {
        $secret = MobilePayConfig::webhookSecret();
        if (! $secret) {
            return true;
        } // not yet configured — accept (e.g. dev/test)

        $signature = $request->header('x-ms-content-sha256');
        if (! $signature) {
            return false;
        }
        $expected = base64_encode(hash('sha256', $payload, true));

        return hash_equals($expected, $signature);
    }

    private function handleAgreement(array $event): void
    {
        $agreementId = $this->extract($event, ['agreementId', 'agreementExternalId', 'data.agreementId']);
        if (! $agreementId) {
            return;
        }

        $enrollment = Enrollment::where('mobilepay_agreement_id', $agreementId)->first();
        if (! $enrollment) {
            return;
        }

        $status = strtoupper((string) $this->extract($event, ['status', 'data.status']));
        $name = strtolower((string) ($event['name'] ?? $event['eventType'] ?? ''));
        $activated = $status === 'ACTIVE' || str_contains($name, 'activat');

        if ($activated) {
            $enrollment->status = 'active';
            $enrollment->enrolled_at = $enrollment->enrolled_at ?: now();
            $enrollment->canceled_at = null;
            $enrollment->cancel_at_period_end = false;
            // Grant the first period (the initial charge covers it).
            if (! $enrollment->current_period_end || $enrollment->current_period_end->isPast()) {
                $enrollment->current_period_end = now()->addMonth();
            }
            $enrollment->save();

            return;
        }

        if (in_array($status, ['STOPPED', 'EXPIRED'], true) || str_contains($name, 'stop') || str_contains($name, 'expir')) {
            $enrollment->update(['status' => 'canceled', 'canceled_at' => now()]);
        }
    }

    private function handleCharge(array $event): void
    {
        $agreementId = $this->extract($event, ['agreementId', 'data.agreementId']);
        $chargeId = $this->extract($event, ['chargeId', 'data.chargeId']);
        if (! $agreementId) {
            return;
        }

        $enrollment = Enrollment::where('mobilepay_agreement_id', $agreementId)->first();
        if (! $enrollment) {
            return;
        }

        $status = strtoupper((string) $this->extract($event, ['status', 'data.status']));
        $name = strtolower((string) ($event['name'] ?? $event['eventType'] ?? ''));

        $payment = $chargeId
            ? Payment::where('provider', 'mobilepay')->where('external_id', $chargeId)->first()
            : null;

        $captured = in_array($status, ['CHARGED', 'CAPTURED'], true) || str_contains($name, 'captur') || str_contains($name, 'charged');
        $failed = $status === 'FAILED' || str_contains($name, 'fail');

        if ($captured) {
            $payment?->update(['status' => Payment::CAPTURED]);
            // Extend access by one month from the later of now / current end.
            $base = $enrollment->current_period_end && $enrollment->current_period_end->isFuture()
                ? $enrollment->current_period_end
                : Carbon::now();
            $enrollment->current_period_end = $base->copy()->addMonth();
            if ($enrollment->status === 'past_due') {
                $enrollment->status = 'active';
            }
            $enrollment->save();

            return;
        }

        if ($failed) {
            $payment?->update(['status' => Payment::FAILED]);
            if ($enrollment->status !== 'past_due') {
                $enrollment->update(['status' => 'past_due']);
                EnrollmentNotifier::notifyPastDue($enrollment);
            }
        }
    }

    /** Read the first present key from an event, supporting dot-paths and nested `data`. */
    private function extract(array $event, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($event, $key);
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}
