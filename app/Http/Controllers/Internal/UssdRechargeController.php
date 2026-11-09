<?php

namespace App\Http\Controllers\Internal;

use App\Exceptions\AmountMismatchException;
use App\Exceptions\PaymentInitFailedException;
use App\Http\Controllers\Controller;
use App\Models\UssdPaymentIntent;
use App\Services\UssdPaymentService;
use App\Services\UssdSubscriberService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class UssdRechargeController extends Controller
{
    public function __construct(
        private UssdSubscriberService $subscribers,
        private UssdPaymentService $payments,
    ) {
    }

    public function initiate(Request $request): JsonResponse
    {
        if ($this->rechargeUnavailable()) {
            return $this->error(
                'RECHARGE_UNAVAILABLE',
                'Recharge is temporarily unavailable.',
                503,
            );
        }

        $data = $request->validate([
            'msisdn' => ['required', 'string', 'max:20'],
            'authusername' => ['required', 'regex:/^\d+$/'],
            'amount' => ['required', 'numeric', 'decimal:0,2'],
            'network' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $local = $this->subscribers->localMsisdn($data['msisdn']);

        if ($local === null) {
            return $this->error('INVALID_MSISDN', 'Enter a valid phone number.', 422);
        }

        $subscriber = $this->subscribers->resolve($local);

        if (!$subscriber) {
            return $this->error('NOT_REGISTERED', 'This phone number is not registered.', 404);
        }

        if (!$this->subscribers->isActive($subscriber)) {
            return $this->error('ACCOUNT_INACTIVE', 'This Altfon account is not active.', 403);
        }

        $account = $this->subscribers->accountFor($subscriber, $data['authusername']);

        if (!$account) {
            return $this->error('FORBIDDEN', 'This SIP number does not belong to you.', 403);
        }

        $amount = round((float) $data['amount'], 2);
        $min = (float) config('services.ussd.recharge_min', 1);
        $max = (float) config('services.ussd.recharge_max', 500);

        if ($amount < $min || $amount > $max) {
            return $this->error(
                'INVALID_AMOUNT',
                'Enter an amount between GHS ' . $this->formatAmount($min) . ' and GHS ' . $this->formatAmount($max) . '.',
                422,
            );
        }

        $network = $this->resolveNetwork($data['network'] ?? null, $local);

        if ($network === null) {
            return $this->error('INVALID_NETWORK', 'Select a valid mobile network.', 422);
        }

        $msisdn = $this->subscribers->internationalMsisdn($data['msisdn']);

        if ($msisdn === null) {
            return $this->error('INVALID_MSISDN', 'Enter a valid phone number.', 422);
        }

        $rateLimitKey = 'ussd-recharge:' . $msisdn;
        $maxAttempts = (int) config('services.ussd.recharge_rate_limit_max', 5);
        $windowSeconds = max((int) config('services.ussd.recharge_rate_limit_window_seconds', 600), 1);

        if ($maxAttempts > 0) {
            if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
                $response = $this->error(
                    'RATE_LIMITED',
                    'Too many recharge attempts. Please try again later.',
                    429,
                );
                $response->headers->set('Retry-After', (string) RateLimiter::availableIn($rateLimitKey));

                return $response;
            }

            RateLimiter::hit($rateLimitKey, $windowSeconds);
        }

        try {
            $intent = $this->payments->initiate(
                $subscriber,
                $account,
                $msisdn,
                $amount,
                $network,
            );
        } catch (PaymentInitFailedException $exception) {
            return $this->error('PAYMENT_INIT_FAILED', $exception->getMessage(), 502);
        }

        return $this->intentResponse($intent);
    }

    public function submitOtp(Request $request): JsonResponse
    {
        if ($this->rechargeUnavailable()) {
            return $this->error(
                'RECHARGE_UNAVAILABLE',
                'Recharge is temporarily unavailable.',
                503,
            );
        }

        $data = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'msisdn' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'string', 'regex:/^\d{4,8}$/'],
        ]);

        $msisdn = $this->subscribers->internationalMsisdn($data['msisdn']);

        if ($msisdn === null) {
            return $this->error('INVALID_MSISDN', 'Enter a valid phone number.', 422);
        }

        $intent = $this->payments->submitOtp($data['reference'], $msisdn, $data['otp']);

        if (!$intent) {
            return $this->error('NOT_FOUND', 'Payment not found.', 404);
        }

        return $this->intentResponse($intent);
    }

    public function status(Request $request, string $reference): JsonResponse
    {
        $data = $request->validate([
            'msisdn' => ['required', 'string', 'max:20'],
        ]);

        $msisdn = $this->subscribers->internationalMsisdn($data['msisdn']);

        if ($msisdn === null) {
            return $this->error('INVALID_MSISDN', 'Enter a valid phone number.', 422);
        }

        $intent = $this->payments->findIntent($reference, $msisdn);

        if (!$intent) {
            return $this->error('NOT_FOUND', 'Payment not found.', 404);
        }

        return $this->intentResponse($this->payments->refresh($intent));
    }

    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'externalRef' => ['sometimes', 'nullable', 'string', 'max:128'],
            'paidAmount' => ['required', 'numeric'],
            'providerStatus' => ['required', 'in:success,failed'],
        ]);

        try {
            $intent = $this->payments->confirm(
                $data['reference'],
                $data['externalRef'] ?? null,
                (float) $data['paidAmount'],
                $data['providerStatus'],
            );
        } catch (ModelNotFoundException) {
            return $this->error('NOT_FOUND', 'Payment not found.', 404);
        } catch (AmountMismatchException $exception) {
            return $this->error('AMOUNT_MISMATCH', $exception->getMessage(), 409);
        }

        return $this->intentResponse($intent);
    }

    private function intentResponse(UssdPaymentIntent $intent): JsonResponse
    {
        $data = [
            'reference' => $intent->reference,
            'state' => $intent->status,
            'requiresOtp' => (bool) $intent->requires_otp,
            'message' => $this->payments->messageForState($intent->status),
            'transactionId' => $intent->status === UssdPaymentIntent::STATUS_COMPLETED
                ? $intent->reference
                : null,
            'balance' => $intent->status === UssdPaymentIntent::STATUS_COMPLETED
                ? $this->payments->balanceFor($intent->authusername)
                : null,
        ];

        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
    }

    private function resolveNetwork(?string $network, string $localMsisdn): ?string
    {
        $network = strtoupper(trim((string) $network));

        if (in_array($network, ['MTN', 'VODAFONE', 'TELECEL', 'AIRTELTIGO'], true)) {
            return $network;
        }

        return match (substr($localMsisdn, 0, 3)) {
            '024', '053', '054', '055', '059' => 'MTN',
            '020', '050' => 'VODAFONE',
            '026', '027', '056', '057' => 'AIRTELTIGO',
            default => null,
        };
    }

    private function rechargeUnavailable(): bool
    {
        if (!config('services.ussd.recharge_enabled', false)) {
            return true;
        }

        $provider = strtolower((string) config('services.ussd.payment_provider', 'fake'));

        if (!in_array($provider, ['fake', 'paystack'], true)) {
            return true;
        }

        if (app()->environment('production') && $provider === 'fake') {
            return true;
        }

        if ($provider === 'paystack' && trim((string) config('services.paystack.secret_key', '')) === '') {
            return true;
        }

        return false;
    }

    private function formatAmount(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'status' => false,
            'code' => $code,
            'message' => $message,
        ], $status);
    }
}
