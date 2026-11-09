<?php

namespace App\Services;

use App\Models\PrepaidCredit;
use App\Models\SubscriberAuthModel;
use App\Models\SubscribersModel;
use App\Models\UssdPaymentIntent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PrepaidCreditService
{
    public const MAX_TOPUP_ATTEMPTS = 5;

    public function __construct(private BoothApiService $booth)
    {
    }

    /**
     * Record a paid credit and top up the SIP exactly once.
     *
     * Safe to call from the mobile/web verify endpoint, the Paystack webhook
     * and the USSD confirmation path; the unique transaction id plus the
     * status claim prevent double top-ups.
     */
    public function credit(
        string $authusername,
        float $amount,
        string $transactionId,
        string $source = 'unknown',
    ): PrepaidCredit {
        try {
            $credit = DB::transaction(function () use ($authusername, $amount, $transactionId, $source) {
                $existing = PrepaidCredit::where('transaction_id', $transactionId)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing;
                }

                return PrepaidCredit::create([
                    'authusername' => $authusername,
                    'amount' => $amount,
                    'transaction_id' => $transactionId,
                    'status' => PrepaidCredit::STATUS_PENDING_TOPUP,
                    'source' => $source,
                    'topup_attempts' => 0,
                    'paid_at' => now(),
                ]);
            });
        } catch (QueryException $exception) {
            // Two writers raced on the unique transaction_id; use the winner.
            $credit = PrepaidCredit::where('transaction_id', $transactionId)->first();

            if (!$credit) {
                throw $exception;
            }
        }

        if (in_array($credit->status, [
            PrepaidCredit::STATUS_COMPLETED,
            PrepaidCredit::STATUS_FAILED,
        ], true)) {
            return $credit;
        }

        $this->attemptTopup($credit);

        return $credit->refresh();
    }

    public function retry(PrepaidCredit $credit): bool
    {
        if ($credit->status === PrepaidCredit::STATUS_COMPLETED) {
            return true;
        }

        if ($credit->status === PrepaidCredit::STATUS_FAILED) {
            return false;
        }

        return $this->attemptTopup($credit);
    }

    public function attemptTopup(PrepaidCredit $credit): bool
    {
        $claimed = PrepaidCredit::whereKey($credit->id)
            ->where('status', PrepaidCredit::STATUS_PENDING_TOPUP)
            ->where('topup_attempts', '<', self::MAX_TOPUP_ATTEMPTS)
            ->update([
                'status' => PrepaidCredit::STATUS_PROCESSING,
                'last_attempt_at' => now(),
            ]);

        if ($claimed === 0) {
            return $credit->refresh()->status === PrepaidCredit::STATUS_COMPLETED;
        }

        $credit->refresh();

        $response = $this->booth->topup($credit->authusername, (float) $credit->amount);
        $succeeded = $response !== null && $response->successful();
        $attempts = $credit->topup_attempts + 1;

        if ($succeeded) {
            $credit->update([
                'status' => PrepaidCredit::STATUS_COMPLETED,
                'failure_reason' => null,
                'topup_attempts' => $attempts,
                'last_attempt_at' => now(),
            ]);

            $credit->refresh();

            $this->syncIntent($credit);
            $this->sendSuccessSms($credit);

            return true;
        }

        $exhausted = $attempts >= self::MAX_TOPUP_ATTEMPTS;

        $credit->update([
            'status' => $exhausted
                ? PrepaidCredit::STATUS_FAILED
                : PrepaidCredit::STATUS_PENDING_TOPUP,
            'failure_reason' => 'Booth top-up failed.',
            'topup_attempts' => $attempts,
            'last_attempt_at' => now(),
        ]);

        $credit->refresh();

        $this->syncIntent($credit);

        if ($exhausted) {
            Log::critical('Prepaid credit top-up failed after max attempts', [
                'transaction_id' => $credit->transaction_id,
                'authusername' => $credit->authusername,
                'attempts' => $attempts,
            ]);
        } else {
            Log::warning('Prepaid credit top-up failed', [
                'transaction_id' => $credit->transaction_id,
                'authusername' => $credit->authusername,
                'attempts' => $attempts,
            ]);
        }

        return false;
    }

    private function syncIntent(PrepaidCredit $credit): void
    {
        $intent = UssdPaymentIntent::where('reference', $credit->transaction_id)->first();

        if (!$intent) {
            return;
        }

        $completed = $credit->status === PrepaidCredit::STATUS_COMPLETED;

        $intent->update([
            'status' => $completed
                ? UssdPaymentIntent::STATUS_COMPLETED
                : UssdPaymentIntent::STATUS_PENDING_TOPUP,
            'failure_reason' => $completed
                ? null
                : ($credit->failure_reason ?: 'Booth top-up failed.'),
            'topup_attempts' => $credit->topup_attempts,
            'paid_at' => $intent->paid_at ?? $credit->paid_at,
        ]);
    }

    private function sendSuccessSms(PrepaidCredit $credit): void
    {
        if ($credit->source === 'admin') {
            return;
        }

        try {
            $account = SubscriberAuthModel::where('authusername', $credit->authusername)->first();

            if (!$account) {
                return;
            }

            $subscriber = SubscribersModel::where('subscriberid', $account->subscriberid)->first();
            $phone = $subscriber?->phonenumber;

            if (!$phone) {
                return;
            }

            $amount = number_format((float) $credit->amount, 2, '.', '');

            queue_otp_sms($phone, "Altfon: Your recharge of GHS {$amount} was successful.");
        } catch (Throwable $exception) {
            Log::warning('Prepaid credit success SMS failed to dispatch', [
                'transaction_id' => $credit->transaction_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
