<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FloatingDevice;
use App\Models\FloatingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FloatingAdminController extends Controller
{
    public function index()
    {
        $settings = FloatingSetting::current();
        $devices = FloatingDevice::orderBy('sort_order')->orderBy('id')->get();

        return view('admin.floating.index', compact('settings', 'devices'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slot_duration_minutes' => ['required', 'integer', 'in:15,30,45,60,75,90,120'],
            'open_from' => ['required', 'date_format:H:i'],
            'open_to' => ['required', 'date_format:H:i', 'after:open_from'],
            'days_open' => ['required', 'array', 'min:1'],
            'days_open.*' => ['in:mon,tue,wed,thu,fri,sat,sun'],
            'price_kr_single' => ['required', 'numeric', 'min:0', 'max:100000'],
            'price_kr_double' => ['required', 'numeric', 'min:0', 'max:100000'],
            'cancel_cutoff_hours' => ['required', 'integer', 'min:0', 'max:168'],
        ]);

        $settings = FloatingSetting::current();

        $newSingle = (int) round(((float) $data['price_kr_single']) * 100);
        $newDouble = (int) round(((float) $data['price_kr_double']) * 100);

        $settings->fill([
            'slot_duration_minutes' => $data['slot_duration_minutes'],
            'open_from' => $data['open_from'].':00',
            'open_to' => $data['open_to'].':00',
            'days_open' => implode(',', $data['days_open']),
            'price_cents' => $newSingle, // keep legacy column in sync with single
            'price_cents_single' => $newSingle,
            'price_cents_double' => $newDouble,
            'cancel_cutoff_hours' => $data['cancel_cutoff_hours'],
        ])->save();

        return back()->with('status', 'Indstillinger gemt.');
    }

    public function storeDevice(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', 'in:'.implode(',', FloatingDevice::TYPES)],
        ]);
        $max = (int) FloatingDevice::max('sort_order');
        FloatingDevice::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'sort_order' => $max + 1,
            'is_active' => true,
        ]);

        return back()->with('status', 'Tank tilføjet.');
    }

    public function updateDevice(Request $request, FloatingDevice $device): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', 'in:'.implode(',', FloatingDevice::TYPES)],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $device->update([
            'name' => $data['name'],
            'type' => $data['type'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Tank opdateret.');
    }

    public function destroyDevice(FloatingDevice $device): RedirectResponse
    {
        $device->delete();

        return back()->with('status', 'Tank slettet.');
    }
}
