<?php

namespace App\Providers;

use App\Payments\Gateway;
use App\Payments\MobilePay\MobilePayClient;
use App\Payments\MobilePay\MobilePayProvider;
use App\Payments\Stripe\StripeProvider;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Gateway::class, function () {
            $providers = [
                'stripe' => new StripeProvider,
            ];

            // MobilePay is registered only when present so the gateway falls back
            // to Stripe cleanly while credentials are not yet issued.
            if (class_exists(MobilePayProvider::class)) {
                $providers['mobilepay'] = new MobilePayProvider(
                    new MobilePayClient
                );
            }

            return new Gateway(
                $providers,
                config('payments.primary', 'mobilepay'),
                config('payments.fallback', 'stripe'),
            );
        });
    }
}
