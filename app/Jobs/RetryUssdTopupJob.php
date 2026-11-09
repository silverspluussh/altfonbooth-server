<?php

namespace App\Jobs;

use App\Models\UssdPaymentIntent;
use App\Services\UssdPaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryUssdTopupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $intentId)
    {
    }

    public function handle(UssdPaymentService $payments): void
    {
        $intent = UssdPaymentIntent::find($this->intentId);

        if ($intent) {
            $payments->retryTopup($intent);
        }
    }
}
