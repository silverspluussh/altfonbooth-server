<?php

namespace App\Services\Payments;

use App\Models\UssdPaymentIntent;

final class PaymentResult
{
    public function __construct(
        public string $state,
        public string $message,
        public ?string $externalRef = null,
        public bool $requiresOtp = false,
        public ?string $providerStatus = null,
    ) {
    }

    public static function pending(
        string $message,
        ?string $externalRef = null,
        ?string $providerStatus = null,
    ): self {
        return new self(UssdPaymentIntent::STATUS_PENDING, $message, $externalRef, false, $providerStatus);
    }

    public static function pendingOtp(
        string $message,
        ?string $externalRef = null,
        ?string $providerStatus = null,
    ): self {
        return new self(UssdPaymentIntent::STATUS_PENDING, $message, $externalRef, true, $providerStatus);
    }

    public static function completed(
        string $message,
        ?string $externalRef = null,
        ?string $providerStatus = null,
    ): self {
        return new self(UssdPaymentIntent::STATUS_COMPLETED, $message, $externalRef, false, $providerStatus);
    }

    public static function failed(
        string $message,
        ?string $externalRef = null,
        ?string $providerStatus = null,
    ): self {
        return new self(UssdPaymentIntent::STATUS_FAILED, $message, $externalRef, false, $providerStatus);
    }
}
