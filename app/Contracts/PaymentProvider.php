<?php

namespace App\Contracts;

use App\Models\UssdPaymentIntent;
use App\Services\Payments\PaymentResult;

interface PaymentProvider
{
    public function initiate(UssdPaymentIntent $intent): PaymentResult;

    public function status(UssdPaymentIntent $intent): PaymentResult;
}
