<?php

namespace App\Console\Commands;

use App\Jobs\ReconcilePrepaidCreditsJob;
use App\Jobs\ReconcileUssdPaymentsJob;
use App\Models\PrepaidCredit;
use App\Models\UssdPaymentIntent;
use App\Services\PrepaidCreditService;
use App\Services\UssdPaymentService;
use Illuminate\Console\Command;

class PaymentsReconcile extends Command
{
    protected $signature = 'payments:reconcile {reference? : Reconcile one intent or credit by reference}';


    protected $description = 'Reconcile USSD payment intents and retry pending SIP top-ups';

    public function handle(UssdPaymentService $payments, PrepaidCreditService $credits): int
    {
        $reference = $this->argument('reference');

        if (is_string($reference) && $reference !== '') {
            return $this->reconcileOne($reference, $payments, $credits);
        }

        (new ReconcileUssdPaymentsJob())->handle($payments);
        (new ReconcilePrepaidCreditsJob())->handle($credits);

        $this->info('Payment reconciliation completed.');

        return self::SUCCESS;
    }

    private function reconcileOne(
        string $reference,
        UssdPaymentService $payments,
        PrepaidCreditService $credits,
    ): int {
        $found = false;

        $intent = UssdPaymentIntent::where('reference', $reference)->first();

        if ($intent) {
            $found = true;
            $intent = $payments->refresh($intent);
            $this->line("intent {$intent->reference}: {$intent->status}");
        }

        $credit = PrepaidCredit::where('transaction_id', $reference)->first();

        if ($credit) {
            $found = true;
            $credits->retry($credit);
            $credit->refresh();
            $this->line("credit {$credit->transaction_id}: {$credit->status} (attempts {$credit->topup_attempts})");
        }

        if (!$found) {
            $this->error("No payment intent or credit found for {$reference}.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
