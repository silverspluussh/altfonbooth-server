<?php

namespace App\Providers;

use App\Contracts\PaymentProvider;
use App\Services\Payments\FakePaymentProvider;
use App\Services\Payments\PaystackPaymentProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentProvider::class, function () {
            $provider = strtolower((string) config('services.ussd.payment_provider', 'fake'));

            return match ($provider) {
                'paystack' => new PaystackPaymentProvider(),
                default => new FakePaymentProvider(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
