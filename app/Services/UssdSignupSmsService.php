<?php

namespace App\Services;

use App\Jobs\SendOtpSmsJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class UssdSignupSmsService
{
    public const REASON_NOT_REGISTERED = 'not_registered';
    public const REASON_INACTIVE = 'inactive';
    public const REASON_NO_NUMBER = 'no_number';

    public function notify(string $localMsisdn, string $reason): void
    {
        $msisdn = trim($localMsisdn);

        if ($msisdn === '') {
            return;
        }

        $message = $this->messageFor($reason);

        if ($message === null) {
            return;
        }

        $ttl = max((int) config('services.ussd.signup_sms_ttl_seconds', 86400), 1);
        $cacheKey = 'ussd-signup-sms:' . $msisdn;

        if (!Cache::add($cacheKey, true, $ttl)) {
            Log::info('Skipped USSD signup SMS (rate limited)', [
                'msisdn' => $msisdn,
                'reason' => $reason,
            ]);

            return;
        }

        $delay = max((int) config('services.ussd.signup_sms_delay_seconds', 5), 0);

        try {
            if ($delay > 0) {
                SendOtpSmsJob::dispatch($msisdn, $message)
                    ->delay(now()->addSeconds($delay));
            } else {
                SendOtpSmsJob::dispatch($msisdn, $message);
            }

            Log::info('Queued USSD signup SMS', [
                'msisdn' => $msisdn,
                'reason' => $reason,
                'delay_seconds' => $delay,
            ]);
        } catch (Throwable $exception) {
            Cache::forget($cacheKey);

            Log::error('Failed to queue USSD signup SMS', [
                'msisdn' => $msisdn,
                'reason' => $reason,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function messageFor(string $reason): ?string
    {
        $url = (string) config('services.ussd.signup_url', 'https://www.altfon.com');

        return match ($reason) {
            self::REASON_NOT_REGISTERED => "This number does not have an Altfon account. Visit {$url} to sign up.",
            self::REASON_INACTIVE => "Your Altfon account is not active. Visit {$url} or contact support.",
            self::REASON_NO_NUMBER => "No Altfon number is linked to your account. Visit {$url} or contact support.",
            default => null,
        };
    }
}
