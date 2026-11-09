<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProvider;
use App\Models\SubscribersModel;
use App\Models\UssdPaymentIntent;
use App\Support\Msisdn;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PaystackPaymentProvider implements PaymentProvider
{
    public function initiate(UssdPaymentIntent $intent): PaymentResult
    {
        $payload = [
            'email' => $this->emailFor($intent),
            'amount' => $this->toSubunit($intent),
            'currency' => (string) config('services.paystack.currency', 'GHS'),
            'reference' => $intent->reference,
            'mobile_money' => [
                // Paystack Ghana expects the local form, e.g. 0551234987.
                'phone' => Msisdn::normalize($intent->msisdn) ?? $intent->msisdn,
                'provider' => $this->providerCode($intent->network),
            ],
            'metadata' => [
                'authusername' => $intent->authusername,
                'subscriberid' => $intent->subscriberid,
                'source' => 'ussd',
            ],
        ];

        return $this->mapCharge($this->request('post', '/charge', $payload), $intent);
    }

    public function status(UssdPaymentIntent $intent): PaymentResult
    {
        $path = '/charge/' . rawurlencode($intent->reference);

        return $this->mapCharge($this->request('get', $path), $intent);
    }

    public function submitOtp(UssdPaymentIntent $intent, string $otp): PaymentResult
    {
        $payload = [
            'otp' => $otp,
            'reference' => $intent->reference,
        ];

        return $this->mapCharge($this->request('post', '/charge/submit_otp', $payload), $intent);
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $secret = trim((string) config('services.paystack.secret_key', ''));

        if ($secret === '') {
            throw new RuntimeException('Paystack is not configured.');
        }

        $baseUrl = rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/');
        $timeout = max((int) config('services.paystack.timeout', 15), 1);

        $client = Http::withToken($secret)
            ->acceptJson()
            ->timeout($timeout)
            ->connectTimeout(min($timeout, 5));

        try {
            $response = $method === 'get'
                ? $client->get($baseUrl . $path)
                : $client->post($baseUrl . $path, $payload);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not reach Paystack.', 0, $exception);
        }

        $body = $response->json();

        if (!$response->successful()) {
            $message = is_array($body) && isset($body['message'])
                ? (string) $body['message']
                : 'Paystack request failed.';

            Log::warning('Paystack request failed', [
                'path' => $path,
                'http_status' => $response->status(),
                'message' => $message,
                'code' => is_array($body) ? ($body['code'] ?? null) : null,
                'type' => is_array($body) ? ($body['type'] ?? null) : null,
                'next_step' => is_array($body) ? ($body['meta']['nextStep'] ?? null) : null,
            ]);

            throw new RuntimeException($message);
        }

        if (!is_array($body)) {
            throw new RuntimeException('Paystack returned an invalid response.');
        }

        return $body;
    }

    private function mapCharge(array $body, UssdPaymentIntent $intent): PaymentResult
    {
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        if ($data === []) {
            $message = (string) ($body['message'] ?? 'Paystack returned an empty charge response.');

            Log::warning('Paystack charge response without data', [
                'reference' => $intent->reference,
                'top_status' => $body['status'] ?? null,
                'message' => $message,
                'code' => $body['code'] ?? null,
                'type' => $body['type'] ?? null,
                'next_step' => $body['meta']['nextStep'] ?? null,
            ]);

            throw new RuntimeException($message);
        }

        $status = strtolower((string) ($data['status'] ?? 'pending'));
        $externalRef = (string) ($data['id'] ?? $data['reference'] ?? $intent->reference);

        $context = [
            'reference' => $intent->reference,
            'top_status' => $body['status'] ?? null,
            'data_status' => $status,
            'gateway_response' => $data['gateway_response'] ?? null,
            'message' => $data['message'] ?? null,
            'paystack_message' => $body['message'] ?? null,
        ];

        if (in_array($status, ['failed', 'reversed', 'abandoned'], true)) {
            Log::warning('Paystack charge declined', $context);
        } else {
            Log::info('Paystack charge response', $context);
        }

        return match (true) {
            $status === 'success' => PaymentResult::completed(
                'Payment successful.',
                $externalRef,
                $status,
            ),
            $status === 'send_otp' => PaymentResult::pendingOtp(
                'Enter the OTP sent to your phone.',
                $externalRef,
                $status,
            ),
            in_array($status, ['failed', 'reversed', 'abandoned'], true) => PaymentResult::failed(
                $this->failureMessage($data, $body),
                $externalRef,
                $status,
            ),
            default => PaymentResult::pending(
                'Approve the prompt on your phone.',
                $externalRef,
                $status,
            ),
        };
    }

    private function failureMessage(array $data, array $body): string
    {
        $message = trim((string) (
            $data['gateway_response']
            ?? $data['message']
            ?? $body['message']
            ?? ''
        ));

        return $message !== '' ? $message : 'Payment failed or was cancelled.';
    }

    private function toSubunit(UssdPaymentIntent $intent): int
    {
        return (int) round(((float) $intent->amount) * 100);
    }

    private function providerCode(?string $network): string
    {
        return match (strtoupper(trim((string) $network))) {
            'MTN' => 'mtn',
            'VODAFONE', 'TELECEL' => 'vod',
            'AIRTELTIGO' => 'atl',
            default => throw new RuntimeException('This mobile network is not supported for payment.'),
        };
    }

    private function emailFor(UssdPaymentIntent $intent): string
    {
        $email = SubscribersModel::where('subscriberid', $intent->subscriberid)->value('emailaddress');
        $email = is_string($email) ? trim($email) : '';

        if ($this->isValidEmail($email)) {
            return $email;
        }

        $fallback = trim((string) config('services.paystack.fallback_email', ''));

        if ($this->isValidEmail($fallback)) {
            return $fallback;
        }

        throw new RuntimeException('An email address is required to start the payment.');
    }

    private function isValidEmail(string $email): bool
    {
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
