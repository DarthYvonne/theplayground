<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Support\StripeConfig;
use App\Support\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function edit(Request $request) {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function billing(Request $request) {
        $user = $request->user();
        $enrollments = Enrollment::with('course')
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'pending'])
            ->orderByDesc('enrolled_at')
            ->get();
        return view('profile.billing', [
            'user' => $user,
            'enrollments' => $enrollments,
            'stripeConfigured' => StripeConfig::isConfigured(),
        ]);
    }

    public function billingPortal(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!StripeConfig::isConfigured() || !$user->stripe_id) {
            return back()->withErrors(['billing' => 'Du har ingen Stripe-konto endnu. Tilmeld dig et hold for at oprette en.']);
        }
        $url = StripeService::customerPortalUrl($user, route('profile.billing'));
        if (!$url) return back()->withErrors(['billing' => 'Kunne ikke åbne Stripe-portalen.']);
        return redirect()->away($url);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'email' => ['required','email','unique:users,email,' . $user->id],
            'phone' => ['nullable','string','max:40'],
            'about' => ['nullable','string','max:2000'],
            'picture' => ['nullable','image','max:4096'],
            'password' => ['nullable','confirmed','min:8'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone = $data['phone'] ?? null;
        $user->about = $data['about'] ?? null;
        if ($request->hasFile('picture')) {
            if ($user->picture_path) Storage::disk('public')->delete($user->picture_path);
            $user->picture_path = $request->file('picture')->store('avatars', 'public');
        }
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return back()->with('status', 'Profilen er opdateret.');
    }
}
