<?php

namespace App\Jobs;

use App\Models\PrepaidCredit;
use App\Services\PrepaidCreditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReconcilePrepaidCreditsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function handle(PrepaidCreditService $credits): void
    {
        // A crashed worker can leave a claim behind; release it for retry.
        PrepaidCredit::where('status', PrepaidCredit::STATUS_PROCESSING)
            ->where(function ($query) {
                $query->whereNull('last_attempt_at')
                    ->orWhere('last_attempt_at', '<=', now()->subMinutes(2));
            })
            ->update(['status' => PrepaidCredit::STATUS_PENDING_TOPUP]);

        PrepaidCredit::where('status', PrepaidCredit::STATUS_PENDING_TOPUP)
            ->where('topup_attempts', '<', PrepaidCreditService::MAX_TOPUP_ATTEMPTS)
            ->where(function ($query) {
                $query->whereNull('last_attempt_at')
                    ->orWhere('last_attempt_at', '<=', now()->subMinute());
            })
            ->chunkById(100, function ($rows) use ($credits) {
                foreach ($rows as $credit) {
                    $credits->retry($credit);
                }
            });
    }
}
