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

        $ctx = CalendarWeek::resolveContext($request);
        $monday = $ctx['monday'];
        $weekStart = $monday->copy()->startOfDay();
        $weekEnd = $monday->copy()->addDays(7)->startOfDay();

        // Slots per day (HH:MM strings) — drawn from settings.
        $slots = $this->buildDailySlots($settings);
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $monday->copy()->addDays($i);
            $days[] = [
                'date' => $d->toDateString(),
                'label' => $this->dayLabel($d),
                'short' => $d->format('d/m'),
                'is_open' => $settings->isOpenOn($d),
                'is_past' => $d->isBefore(Carbon::today()),
            ];
        }

        // Pull bookings overlapping the visible week.
        $bookings = FloatingBooking::where('status', 'active')
            ->whereBetween('slot_start', [$weekStart, $weekEnd])
            ->get()
            ->groupBy(function ($b) {
                return $b->slot_start->toDateString() . ' ' . $b->slot_start->format('H:i');
            });

        $devicesByType = $devices->groupBy('type');
        $totalByType = [
            'single' => $devicesByType->get('single', collect())->count(),
            'double' => $devicesByType->get('double', collect())->count(),
        ];
        $deviceTypeById = $devices->pluck('type', 'id')->all();

        // For each (day, slot) compute: device statuses + my booking (if any).
        $grid = [];
        foreach ($days as $day) {
            $row = [];
            foreach ($slots as $hhmm) {
                $key = $day['date'] . ' ' . $hhmm;
                $taken = $bookings->get($key, collect());
                $takenDeviceIds = $taken->pluck('device_id')->all();
                $myBooking = $taken->firstWhere('user_id', $user?->id);

                $takenByType = ['single' => 0, 'double' => 0];
                foreach ($takenDeviceIds as $id) {
                    $t = $deviceTypeById[$id] ?? null;
                    if (isset($takenByType[$t])) $takenByType[$t]++;
                }
                $freeByType = [
                    'single' => max(0, $totalByType['single'] - $takenByType['single']),
                    'double' => max(0, $totalByType['double'] - $takenByType['double']),
                ];

                $row[$hhmm] = [
                    'taken_device_ids' => $takenDeviceIds,
                    'free_count' => max(0, $devices->count() - count($takenDeviceIds)),
                    'free_by_type' => $freeByType,
                    'mine' => $myBooking,
                ];
            }
            $grid[$day['date']] = $row;
        }

        $myUpcoming = $user
            ? FloatingBooking::with('device')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->where('slot_end', '>=', now())
                ->orderBy('slot_start')
                ->get()
            : collect();

        return view('floating.index', [
            'settings' => $settings,
            'devices' => $devices,
            'days' => $days,
            'slots' => $slots,
            'grid' => $grid,
            'monday' => $monday,
            'myUpcoming' => $myUpcoming,
            'isOwner' => $isOwner,
            'title' => 'Floating',
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
        if (!$isOwner && !$booking->isCancellable($settings->cancel_cutoff_hours)) {
            return back()->withErrors(['cancel' => 'Afbestillingsfristen er passeret. Kontakt os hvis du har brug for hjælp.']);
        }

        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
        ]);
        // TODO: trigger Stripe refund here once we wire payments.
        return back()->with('status', 'Booking aflyst.');
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
