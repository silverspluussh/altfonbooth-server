<?php

namespace App\Http\Controllers;

use App\Exceptions\AmountMismatchException;
use App\Services\UssdPaymentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaystackWebhookController extends Controller
{
    public function __construct(private UssdPaymentService $payments)
    {
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
