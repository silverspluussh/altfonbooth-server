<?php

namespace App\Services;

use App\Contracts\PaymentProvider;
use App\Exceptions\AmountMismatchException;
use App\Exceptions\PaymentInitFailedException;
use App\Jobs\RetryUssdTopupJob;
use App\Models\PrepaidCredit;
use App\Models\SubscriberAuthModel;
use App\Models\SubscribersModel;
use App\Models\UssdPaymentIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class UssdPaymentService
{
    public const MAX_TOPUP_ATTEMPTS = 5;

    public function __construct(
        private BoothApiService $booth,
        private PaymentProvider $provider,
    ) {
    }

    public function findIntent(string $reference, ?string $msisdn = null): ?UssdPaymentIntent
    {
        return UssdPaymentIntent::where('reference', $reference)
            ->when($msisdn !== null, fn ($query) => $query->where('msisdn', $msisdn))
            ->first();
    }

    public function initiate(
        SubscribersModel $subscriber,
        SubscriberAuthModel $account,
        string $msisdn,
        float $amount,
        ?string $network,
    ): UssdPaymentIntent {
        $existing = UssdPaymentIntent::where('msisdn', $msisdn)
            ->where('authusername', $account->authusername)
            ->where('amount', $amount)
            ->where('status', UssdPaymentIntent::STATUS_PENDING)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $intent = UssdPaymentIntent::create([
            'reference' => $this->createReference(),
            'subscriberid' => $subscriber->subscriberid,
            'authusername' => $account->authusername,
            'msisdn' => $msisdn,
            'amount' => $amount,
            'network' => $network,
            'provider' => (string) config('services.ussd.payment_provider', 'fake'),
            'status' => UssdPaymentIntent::STATUS_PENDING,
        ]);

        try {
            $result = $this->provider->initiate($intent);
        } catch (Throwable $exception) {
            $intent->update([
                'status' => UssdPaymentIntent::STATUS_FAILED,
                'failure_reason' => $exception->getMessage(),
            ]);

            Log::error('USSD payment initiation failed', [
                'reference' => $intent->reference,
                'error' => $exception->getMessage(),
            ]);

            throw new PaymentInitFailedException('Could not start the payment.', 0, $exception);
        }

        UssdPaymentIntent::whereKey($intent->id)
            ->where('status', UssdPaymentIntent::STATUS_PENDING)
            ->update([
                'external_ref' => $result->externalRef,
                'requires_otp' => $result->requiresOtp,
                'updated_at' => now(),
            ]);

        $intent->refresh();

        // A webhook or a concurrent confirm may have settled the intent already.
        if ($intent->status !== UssdPaymentIntent::STATUS_PENDING) {
            return $intent;
        }

        if ($result->state === UssdPaymentIntent::STATUS_FAILED) {
            $intent->update([
                'status' => UssdPaymentIntent::STATUS_FAILED,
                'failure_reason' => $result->message,
                'requires_otp' => false,
            ]);

            Log::warning('USSD payment declined by provider', [
                'reference' => $intent->reference,
                'provider' => $intent->provider,
                'reason' => $result->message,
                'provider_status' => $result->providerStatus,
            ]);

            return $intent->refresh();
        }

        if ($result->state === UssdPaymentIntent::STATUS_COMPLETED) {
            return $this->confirm(
                $intent->reference,
                $result->externalRef,
                (float) $intent->amount,
                'success',
            );
        }

        return $intent;
    }

    public function refresh(UssdPaymentIntent $intent): UssdPaymentIntent
    {
        if (in_array($intent->status, [
            UssdPaymentIntent::STATUS_COMPLETED,
            UssdPaymentIntent::STATUS_FAILED,
            UssdPaymentIntent::STATUS_PENDING_TOPUP,
        ], true)) {
            return $intent;
        }

        try {
            $result = $this->provider->status($intent);
        } catch (Throwable $exception) {
            Log::warning('USSD payment status check failed', [
                'reference' => $intent->reference,
                'error' => $exception->getMessage(),
            ]);

            return $intent;
        }

        if ($result->state === UssdPaymentIntent::STATUS_COMPLETED) {
            return $this->confirm(
                $intent->reference,
                $result->externalRef,
                (float) $intent->amount,
                'success',
            );
        }

        if ($result->state === UssdPaymentIntent::STATUS_FAILED) {
            $intent->update([
                'status' => UssdPaymentIntent::STATUS_FAILED,
                'failure_reason' => $result->message,
                'external_ref' => $result->externalRef ?: $intent->external_ref,
                'requires_otp' => false,
            ]);

            Log::warning('USSD payment declined during reconciliation', [
                'reference' => $intent->reference,
                'provider' => $intent->provider,
                'reason' => $result->message,
                'provider_status' => $result->providerStatus,
            ]);

            return $intent->refresh();
        }

        UssdPaymentIntent::whereKey($intent->id)
            ->whereIn('status', [
                UssdPaymentIntent::STATUS_PENDING,
                UssdPaymentIntent::STATUS_PROCESSING,
            ])
            ->update([
                'external_ref' => $result->externalRef ?: $intent->external_ref,
                'requires_otp' => $result->requiresOtp,
                'updated_at' => now(),
            ]);

        return $intent->refresh();
    }

    public function submitOtp(string $reference, ?string $msisdn, string $otp): ?UssdPaymentIntent
    {
        $intent = $this->findIntent($reference, $msisdn);

        if (!$intent) {
            return null;
        }

        if ($intent->status !== UssdPaymentIntent::STATUS_PENDING) {
            return $intent;
        }

        try {
            $result = $this->provider->submitOtp($intent, $otp);
        } catch (Throwable $exception) {
            Log::warning('USSD payment OTP submission failed', [
                'reference' => $intent->reference,
                'error' => $exception->getMessage(),
            ]);

            return $intent->refresh();
        }

        if ($intent->refresh()->status !== UssdPaymentIntent::STATUS_PENDING) {
            return $intent;
        }

        if ($result->state === UssdPaymentIntent::STATUS_COMPLETED) {
            return $this->confirm(
                $intent->reference,
                $result->externalRef,
                (float) $intent->amount,
                'success',
            );
        }

        if ($result->state === UssdPaymentIntent::STATUS_FAILED) {
            $intent->update([
                'status' => UssdPaymentIntent::STATUS_FAILED,
                'failure_reason' => $result->message,
                'external_ref' => $result->externalRef ?: $intent->external_ref,
                'requires_otp' => false,
            ]);

            Log::warning('USSD payment OTP declined by provider', [
                'reference' => $intent->reference,
                'provider' => $intent->provider,
                'reason' => $result->message,
                'provider_status' => $result->providerStatus,
            ]);

            return $intent->refresh();
        }

        $intent->update([
            'external_ref' => $result->externalRef ?: $intent->external_ref,
            'requires_otp' => $result->requiresOtp,
        ]);

        return $intent->refresh();
    }

    public function fail(string $reference, ?string $reason = null): ?UssdPaymentIntent
    {
        $intent = UssdPaymentIntent::where('reference', $reference)->first();

        if (!$intent) {
            return null;
        }

        return DB::transaction(function () use ($intent, $reason) {
            $locked = UssdPaymentIntent::whereKey($intent->id)
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                return null;
            }

            if (in_array($locked->status, [
                UssdPaymentIntent::STATUS_COMPLETED,
                UssdPaymentIntent::STATUS_PENDING_TOPUP,
            ], true)) {
                return $locked;
            }

            $locked->update([
                'status' => UssdPaymentIntent::STATUS_FAILED,
                'failure_reason' => $reason ?: 'Payment failed or was cancelled.',
                'requires_otp' => false,
            ]);

            return $locked->refresh();
        });
    }

    public function confirm(
        string $reference,
        ?string $externalRef,
        float $paidAmount,
        string $providerStatus,
    ): UssdPaymentIntent {
        return DB::transaction(function () use ($reference, $externalRef, $paidAmount, $providerStatus) {
            $intent = UssdPaymentIntent::where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (!$intent) {
                throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())
                    ->setModel(UssdPaymentIntent::class, $reference);
            }

            if (abs($paidAmount - (float) $intent->amount) > 0.0001) {
                throw new AmountMismatchException('Paid amount does not match the payment intent.');
            }

            if (strtolower($providerStatus) !== 'success') {
                if (!in_array($intent->status, [
                    UssdPaymentIntent::STATUS_COMPLETED,
                    UssdPaymentIntent::STATUS_PENDING_TOPUP,
                ], true)) {
                    $intent->update([
                        'status' => UssdPaymentIntent::STATUS_FAILED,
                        'failure_reason' => 'Provider reported a failed payment.',
                        'external_ref' => $externalRef ?: $intent->external_ref,
                        'requires_otp' => false,
                    ]);
                }

                return $intent->refresh();
            }

            if ($intent->status === UssdPaymentIntent::STATUS_COMPLETED) {
                return $intent;
            }

            if ($intent->status === UssdPaymentIntent::STATUS_PENDING_TOPUP) {
                $this->attemptTopup($intent);

                return $intent->refresh();
            }

            PrepaidCredit::firstOrCreate(
                ['transaction_id' => $intent->reference],
                [
                    'authusername' => $intent->authusername,
                    'amount' => $intent->amount,
                    'status' => 'completed',
                ],
            );

            $intent->update([
                'status' => UssdPaymentIntent::STATUS_PROCESSING,
                'external_ref' => $externalRef ?: $intent->external_ref,
                'requires_otp' => false,
                'paid_at' => now(),
            ]);

            $this->attemptTopup($intent);

            return $intent->refresh();
        });
    }

    public function retryTopup(UssdPaymentIntent $intent): bool
    {
        return DB::transaction(function () use ($intent) {
            $locked = UssdPaymentIntent::whereKey($intent->id)
                ->lockForUpdate()
                ->first();

            if (
                !$locked
                || $locked->status !== UssdPaymentIntent::STATUS_PENDING_TOPUP
                || $locked->topup_attempts >= self::MAX_TOPUP_ATTEMPTS
            ) {
                return false;
            }

            return $this->attemptTopup($locked, false);
        });
    }

    public function balanceFor(string $authusername): ?float
    {
        $response = $this->booth->getBalance($authusername);
        $balance = $this->booth->extractBalance($response);

        return is_numeric($balance) ? (float) $balance : null;
    }

    public function messageForState(string $state): string
    {
        return match ($state) {
            UssdPaymentIntent::STATUS_PENDING => 'Approve the prompt on your phone.',
            UssdPaymentIntent::STATUS_COMPLETED => 'Payment successful.',
            UssdPaymentIntent::STATUS_FAILED => 'Payment failed or was cancelled.',
            UssdPaymentIntent::STATUS_PENDING_TOPUP => 'Payment received. Credit is being applied.',
            default => 'Payment is processing.',
        };
    }

    private function attemptTopup(UssdPaymentIntent $intent, bool $scheduleRetry = true): bool
    {
        $response = $this->booth->topup($intent->authusername, (float) $intent->amount);
        $succeeded = $response !== null && $response->successful();
        $attempts = $intent->topup_attempts + 1;

        if ($succeeded) {
            $intent->update([
                'status' => UssdPaymentIntent::STATUS_COMPLETED,
                'failure_reason' => null,
                'topup_attempts' => $attempts,
            ]);

            $amount = number_format((float) $intent->amount, 2, '.', '');

            DB::afterCommit(fn () => queue_otp_sms(
                $intent->msisdn,
                "Altfon: Your recharge of GHS {$amount} was successful.",
            ));

            return true;
        }

        $intent->update([
            'status' => UssdPaymentIntent::STATUS_PENDING_TOPUP,
            'failure_reason' => 'Booth top-up failed.',
            'topup_attempts' => $attempts,
        ]);

        if ($attempts >= self::MAX_TOPUP_ATTEMPTS) {
            Log::critical('USSD payment top-up repeatedly failed', [
                'reference' => $intent->reference,
                'authusername' => $intent->authusername,
                'attempts' => $attempts,
            ]);

            return false;
        }

        if ($scheduleRetry) {
            DB::afterCommit(fn () => RetryUssdTopupJob::dispatch($intent->id));
        }

        return false;
    }

    private function createReference(): string
    {
        do {
            $reference = 'ALT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (UssdPaymentIntent::where('reference', $reference)->exists());

        return $reference;
    }
}
