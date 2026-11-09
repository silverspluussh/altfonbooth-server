<?php

namespace App\Http\Controllers;

use App\Exceptions\AmountMismatchException;
use App\Models\SubscriberAuthModel;
use App\Models\UssdPaymentIntent;
use App\Services\PrepaidCreditService;
use App\Services\UssdPaymentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaystackWebhookController extends Controller
{
    public function __construct(
        private UssdPaymentService $payments,
        private PrepaidCreditService $credits,
    ) {
    }

    public function verify(): JsonResponse
    {
        return response()->json(['status' => true, 'message' => 'Paystack webhook endpoint.']);
    }

    public function handle(Request $request): JsonResponse
    {
        $secret = trim((string) config('services.paystack.secret_key', ''));
        $raw = $request->getContent();
        $signature = (string) $request->header('x-paystack-signature', '');

        if (
            $secret === ''
            || $signature === ''
            || !hash_equals(hash_hmac('sha512', $raw, $secret), $signature)
        ) {
            Log::warning('Rejected Paystack webhook with an invalid signature', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => false, 'message' => 'Invalid signature.'], 401);
        }

        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            return response()->json(['status' => true]);
        }

        $event = (string) ($payload['event'] ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        try {
            switch ($event) {
                case 'charge.success':
                    $this->onSuccess($data);
                    break;
                case 'charge.failed':
                    $this->onFailed($data);
                    break;
                default:
                    Log::info('Ignoring Paystack webhook event', ['event' => $event]);
            }
        } catch (Throwable $exception) {
            Log::error('Paystack webhook processing failed', [
                'event' => $event,
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json(['status' => true]);
    }

    private function onSuccess(array $data): void
    {
        $reference = (string) ($data['reference'] ?? '');

        if ($reference === '' || !isset($data['amount'])) {
            Log::warning('Paystack charge.success webhook is missing a reference or amount', [
                'data' => $data,
            ]);

            return;
        }

        $paidAmount = ((float) $data['amount']) / 100;
        $externalRef = isset($data['id']) ? (string) $data['id'] : null;

        if (!UssdPaymentIntent::where('reference', $reference)->exists()) {
            $this->creditWebPayment($data, $reference, $paidAmount);

            return;
        }

        try {
            $this->payments->confirm($reference, $externalRef, $paidAmount, 'success');
        } catch (ModelNotFoundException) {
            Log::warning('Paystack charge.success webhook for an unknown reference', [
                'reference' => $reference,
            ]);
        } catch (AmountMismatchException $exception) {
            Log::critical('Paystack charge.success webhook amount mismatch', [
                'reference' => $reference,
                'paidAmount' => $paidAmount,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Safety net for web/mobile/dashboard payments: credit them even if the
     * client never calls the verify endpoint. The credit service is idempotent.
     */
    private function creditWebPayment(array $data, string $reference, float $amount): void
    {
        $metadata = $data['metadata'] ?? [];

        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true) ?: [];
        }

        if (!is_array($metadata) || $amount <= 0) {
            return;
        }

        $authusername = trim((string) ($metadata['authusername'] ?? ''));
        $subscriberid = trim((string) ($metadata['subscriberid'] ?? ''));

        if ($authusername === '' || $subscriberid === '') {
            Log::info('Ignoring non-USSD Paystack payment without identifiable metadata', [
                'reference' => $reference,
            ]);

            return;
        }

        $owns = SubscriberAuthModel::where('authusername', $authusername)
            ->where('subscriberid', $subscriberid)
            ->exists();

        if (!$owns) {
            Log::warning('Paystack webhook for a SIP that does not belong to the subscriber', [
                'reference' => $reference,
                'authusername' => $authusername,
                'subscriberid' => $subscriberid,
            ]);

            return;
        }

        $source = in_array($metadata['source'] ?? null, ['web', 'mobile'], true)
            ? $metadata['source']
            : 'paystack';

        $this->credits->credit($authusername, $amount, $reference, $source);
    }

    private function onFailed(array $data): void
    {
        $reference = (string) ($data['reference'] ?? '');

        if ($reference === '') {
            return;
        }

        $reason = trim((string) ($data['gateway_response'] ?? $data['message'] ?? ''));
        $this->payments->fail($reference, $reason !== '' ? $reason : 'Payment failed or was cancelled.');
    }
}
