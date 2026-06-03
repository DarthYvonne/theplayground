<?php

namespace App\Http\Controllers;

use App\Models\FloatingBooking;
use App\Models\FloatingDevice;
use App\Models\FloatingSetting;
use App\Support\CalendarWeek;
use App\Support\StripeConfig;
use App\Support\StripeService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FloatingController extends Controller
{
    public function index(Request $request)
    {
        $settings = FloatingSetting::current();
        $devices = FloatingDevice::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $user = $request->user();
        $isOwner = $user?->isOwner() ?? false;

        $monday = CalendarWeek::resolveContext($request)['monday'];

        $myUpcoming = $user
            ? FloatingBooking::with('device')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->where('slot_end', '>=', now())
                ->orderBy('slot_start')
                ->get()
            : collect();

        // Each tank card fetches its own week of availability from availability() below,
        // so the page only needs the device list and the starting week.
        return view('floating.index', [
            'settings' => $settings,
            'devices' => $devices,
            'weekParam' => CalendarWeek::weekParam($monday),
            'myUpcoming' => $myUpcoming,
            'isOwner' => $isOwner,
            'title' => 'Floating',
        ]);
    }

    /**
     * JSON availability for a single tank in a single week. Drives the per-card
     * week plan + arrows on the floating page (one request per card per week).
     */
    public function availability(Request $request)
    {
        $settings = FloatingSetting::current();
        $device = FloatingDevice::where('is_active', true)->findOrFail($request->integer('device_id'));
        $user = $request->user();

        $monday = CalendarWeek::resolveContext($request)['monday'];
        $weekStart = $monday->copy()->startOfDay();
        $weekEnd = $monday->copy()->addDays(7)->startOfDay();

        $slots = $this->buildDailySlots($settings);

        $bookings = FloatingBooking::where('status', 'active')
            ->where('device_id', $device->id)
            ->whereBetween('slot_start', [$weekStart, $weekEnd])
            ->get()
            ->keyBy(fn ($b) => $b->slot_start->toDateString() . ' ' . $b->slot_start->format('H:i'));

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $monday->copy()->addDays($i);
            $isOpen = $settings->isOpenOn($d);
            $daySlots = [];
            if ($isOpen) {
                foreach ($slots as $hhmm) {
                    $start = Carbon::parse($d->toDateString() . ' ' . $hhmm);
                    $end = $start->copy()->addMinutes($settings->slot_duration_minutes);
                    $booking = $bookings->get($d->toDateString() . ' ' . $hhmm);
                    $status = match (true) {
                        $start->isPast() => 'past',
                        $booking && $user && $booking->user_id === $user->id => 'mine',
                        (bool) $booking => 'full',
                        default => 'free',
                    };
                    $daySlots[] = [
                        'start' => $hhmm,
                        'end' => $end->format('H:i'),
                        'slot_start' => $d->toDateString() . ' ' . $hhmm,
                        'status' => $status,
                    ];
                }
            }
            $days[] = [
                'date' => $d->toDateString(),
                'label' => $this->dayLabel($d),
                'short' => $d->format('d/m'),
                'is_open' => $isOpen,
                'is_today' => $d->isToday(),
                'slots' => $daySlots,
            ];
        }

        $weekEndDay = $monday->copy()->addDays(6);

        return response()->json([
            'device_id' => $device->id,
            'week_param' => CalendarWeek::weekParam($monday),
            'week_label' => 'Uge ' . $monday->isoWeek . ' · ' . $monday->format('d.m') . '–' . $weekEndDay->format('d.m'),
            'prev' => CalendarWeek::weekParam($monday->copy()->subDays(7)),
            'next' => CalendarWeek::weekParam($monday->copy()->addDays(7)),
            'price_label' => $settings->priceLabelFor($device->type),
            'days' => $days,
        ]);
    }

    public function book(Request $request): RedirectResponse
    {
        $user = $request->user();
        $settings = FloatingSetting::current();

        $data = $request->validate([
            'device_id' => ['required','integer','exists:floating_devices,id'],
            'slot_start' => ['required','date_format:Y-m-d H:i'],
        ]);

        $start = Carbon::createFromFormat('Y-m-d H:i', $data['slot_start']);
        $end = $start->copy()->addMinutes($settings->slot_duration_minutes);

        if ($start->isPast()) {
            return back()->withErrors(['slot' => 'Tidspunktet er allerede passeret.']);
        }
        if (!$settings->isOpenOn($start)) {
            return back()->withErrors(['slot' => 'Holdet er lukket den dag.']);
        }

        $device = FloatingDevice::findOrFail($data['device_id']);
        if (!$device->is_active) {
            return back()->withErrors(['slot' => 'Tanken er ikke aktiv.']);
        }

        // Idempotency: if a pending booking for this user+slot already exists, reuse it.
        $existing = FloatingBooking::where('device_id', $device->id)
            ->where('slot_start', $start)
            ->whereIn('status', ['pending','active'])
            ->first();
        if ($existing && $existing->user_id !== $user->id) {
            return back()->withErrors(['slot' => 'Slottet er allerede booket.']);
        }
        if ($existing && $existing->status === 'active') {
            return redirect()->route('floating.index')->with('status', 'Du er allerede booket.');
        }

        $priceCents = $settings->priceCentsFor($device->type);
        $stripePriceId = $settings->stripePriceIdFor($device->type);
        $paidFlow = StripeConfig::isConfigured() && $priceCents > 0;

        if ($paidFlow) {
            if (!$stripePriceId) {
                return back()->withErrors(['slot' => 'Stripe-pris mangler for denne tank-type. Bed admin om at gemme Floating-indstillinger igen.']);
            }
            $booking = $existing ?? new FloatingBooking();
            $booking->fill([
                'user_id' => $user->id,
                'device_id' => $device->id,
                'slot_start' => $start,
                'slot_end' => $end,
                'status' => 'pending',
                'amount_cents' => $priceCents,
            ])->save();

            try {
                $session = StripeService::createOneTimeCheckoutSession(
                    $user,
                    $stripePriceId,
                    route('floating.return') . '?session_id={CHECKOUT_SESSION_ID}',
                    route('floating.index'),
                    ['booking_id' => $booking->id, 'user_id' => $user->id],
                );
                $booking->stripe_session_id = $session['id'] ?? null;
                $booking->save();
                return redirect()->away($session['url']);
            } catch (\Throwable $e) {
                return back()->withErrors(['slot' => 'Stripe-fejl: ' . $e->getMessage()]);
            }
        }

        // Free / no-Stripe flow: create active booking directly.
        DB::transaction(function () use ($user, $device, $start, $end, $priceCents, $existing) {
            $b = $existing ?? new FloatingBooking();
            $b->fill([
                'user_id' => $user->id,
                'device_id' => $device->id,
                'slot_start' => $start,
                'slot_end' => $end,
                'status' => 'active',
                'amount_cents' => $priceCents,
                'paid_at' => null,
            ])->save();
        });

        return redirect()->route('floating.index')->with('status', 'Slot booket.');
    }

    public function returnFromCheckout(Request $request): RedirectResponse
    {
        $sessionId = $request->query('session_id');
        if ($sessionId && StripeConfig::isConfigured()) {
            try {
                $s = StripeService::retrieveCheckoutSession($sessionId);
                $bookingId = (int) ($s['metadata']['booking_id'] ?? 0);
                if ($bookingId && ($s['status'] ?? '') === 'complete') {
                    $booking = FloatingBooking::find($bookingId);
                    if ($booking && $booking->user_id === $request->user()->id) {
                        $booking->status = 'active';
                        $booking->paid_at = now();
                        $booking->stripe_payment_intent_id = $s['payment_intent'] ?? $booking->stripe_payment_intent_id;
                        $booking->save();
                        return redirect()->route('floating.index')->with('status', 'Betaling modtaget. Slot booket.');
                    }
                }
            } catch (\Throwable) {
                // fall through
            }
        }
        return redirect()->route('floating.index')->with('status', 'Betaling modtaget. Din booking opdateres om et øjeblik.');
    }

    public function cancel(Request $request, FloatingBooking $booking): RedirectResponse
    {
        $user = $request->user();
        $settings = FloatingSetting::current();
        $isOwner = $user->isOwner();
        abort_unless($isOwner || $booking->user_id === $user->id, 403);

        if ($booking->isCancelled()) return back();

        // Refund policy: full refund if cancelled before the cutoff window (i.e. while
        // still within `cancel_cutoff_hours` of breathing room before the slot starts),
        // or whenever an owner cancels. After the cutoff a user can still cancel but
        // doesn't get their money back.
        $eligibleForRefund = $isOwner || $booking->isCancellable($settings->cancel_cutoff_hours);
        $refunded = false;

        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
        ]);

        if ($eligibleForRefund && $booking->paid_at && $booking->stripe_payment_intent_id && StripeConfig::isConfigured()) {
            try {
                StripeService::refundPaymentIntent($booking->stripe_payment_intent_id);
                $refunded = true;
            } catch (\Throwable $e) {
                return back()->with('status', 'Booking aflyst, men refundering mislykkedes: ' . $e->getMessage());
            }
        }

        $msg = match (true) {
            $refunded => 'Booking aflyst og betaling refunderet.',
            $eligibleForRefund => 'Booking aflyst.',
            default => 'Booking aflyst. Da afbestillingsfristen var passeret, refunderes betalingen ikke.',
        };
        return back()->with('status', $msg);
    }

    /** @return array<string> HH:MM strings */
    private function buildDailySlots(FloatingSetting $settings): array
    {
        $from = Carbon::createFromFormat('H:i:s', $settings->open_from);
        $to = Carbon::createFromFormat('H:i:s', $settings->open_to);
        $dur = max(15, (int) $settings->slot_duration_minutes);
        $slots = [];
        $cursor = $from->copy();
        while ($cursor->copy()->addMinutes($dur)->lte($to)) {
            $slots[] = $cursor->format('H:i');
            $cursor->addMinutes($dur);
        }
        return $slots;
    }

    private function dayLabel(Carbon $d): string
    {
        $names = ['Mon'=>'Man','Tue'=>'Tir','Wed'=>'Ons','Thu'=>'Tor','Fri'=>'Fre','Sat'=>'Lør','Sun'=>'Søn'];
        return $names[$d->format('D')] ?? $d->format('D');
    }
}
