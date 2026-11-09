<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProvider;
use App\Models\UssdPaymentIntent;

class FakePaymentProvider implements PaymentProvider
{
    public function initiate(UssdPaymentIntent $intent): PaymentResult
    {
        return $this->resolve($intent);
    }

    public function status(UssdPaymentIntent $intent): PaymentResult
    {
        return $this->resolve($intent);
    }

    private function resolve(UssdPaymentIntent $intent): PaymentResult
    {
        $outcome = strtolower((string) config('services.ussd.fake_payment_outcome', 'success'));
        $externalRef = 'FAKE-' . $intent->reference;

        if ($outcome === 'pending') {
            return PaymentResult::pending('Approve the prompt on your phone.', $externalRef);
        }

        $delay = max(0, (int) config('services.ussd.fake_payment_delay_seconds', 2));
        $createdAt = $intent->created_at ?? now();

        if ($delay > 0 && now()->lt($createdAt->copy()->addSeconds($delay))) {
            return PaymentResult::pending('Approve the prompt on your phone.', $externalRef);
        }

        if ($outcome === 'failed') {
            return PaymentResult::failed('Payment failed or was cancelled.', $externalRef);
        }

        return PaymentResult::completed('Payment successful.', $externalRef);
    }
}
