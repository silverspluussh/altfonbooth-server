<?php

namespace App\Jobs;

use App\Models\UssdPaymentIntent;
use App\Services\UssdPaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReconcileUssdPaymentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function handle(UssdPaymentService $payments): void
    {
        UssdPaymentIntent::whereIn('status', [
            UssdPaymentIntent::STATUS_PENDING,
            UssdPaymentIntent::STATUS_PROCESSING,
        ])
            ->where('created_at', '<=', now()->subMinutes(2))
            ->chunkById(100, function ($intents) use ($payments) {
                foreach ($intents as $intent) {
                    $refreshed = $payments->refresh($intent);

                    $createdAt = $refreshed->created_at;

                    if (
                        $refreshed->status === UssdPaymentIntent::STATUS_PENDING
                        && $createdAt !== null
                        && $createdAt->lte(now()->subMinutes(15))
                    ) {
                        $payments->fail($refreshed->reference, 'Payment timed out.');
                    }
                }
            });
    }
}
