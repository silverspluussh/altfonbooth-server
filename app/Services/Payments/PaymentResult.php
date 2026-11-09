<?php

namespace App\Services\Payments;

use App\Models\UssdPaymentIntent;

final class PaymentResult
{
    public function __construct(
        public string $state,
        public string $message,
        public ?string $externalRef = null,
    ) {
    }

    public static function pending(string $message, ?string $externalRef = null): self
    {
        return new self(UssdPaymentIntent::STATUS_PENDING, $message, $externalRef);
    }

    public static function completed(string $message, ?string $externalRef = null): self
    {
        return new self(UssdPaymentIntent::STATUS_COMPLETED, $message, $externalRef);
    }

    public static function failed(string $message, ?string $externalRef = null): self
    {
        return new self(UssdPaymentIntent::STATUS_FAILED, $message, $externalRef);
    }
}
