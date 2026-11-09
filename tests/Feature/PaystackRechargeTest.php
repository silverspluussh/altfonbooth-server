<?php

namespace Tests\Feature;

use App\Models\SubscriberAuthModel;
use App\Models\SubscribersModel;
use App\Models\UssdPaymentIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackRechargeTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ussd.internal_token' => 'test-internal-token',
            'services.ussd.internal_ip_allowlist' => [],
            'services.ussd.recharge_enabled' => true,
            'services.ussd.payment_provider' => 'paystack',
            'services.ussd.recharge_rate_limit_max' => 100,
            'services.paystack.secret_key' => self::SECRET,
            'services.paystack.base_url' => 'https://api.paystack.co',
            'services.paystack.currency' => 'GHS',
            'services.paystack.fallback_email' => 'fallback@example.com',
        ]);
    }

    private function internal()
    {
        return $this->withHeader('X-Internal-Token', 'test-internal-token');
    }

    private function createSubscriber(
        string $subscriberid = 'SUB_PAY1',
        string $phonenumber = '0249335549',
        string $email = 'ama@example.com',
        string $authusername = '7022875',
    ): SubscribersModel {
        $subscriber = SubscribersModel::create([
            'subscriberid' => $subscriberid,
            'fullname' => 'Ama Mensah',
            'username' => strtolower($subscriberid),
            'password' => bcrypt('secret123'),
            'phonenumber' => $phonenumber,
            'emailaddress' => $email,
            'country' => 'Ghana',
            'authusername' => $authusername,
            'switch_status' => 'active',
            'billing_acc_status' => 'active',
        ]);

        SubscriberAuthModel::create([
            'subscriberid' => $subscriberid,
            'authusername' => $authusername,
            'authpassword' => 'secret',
            'status' => 'active',
        ]);

        return $subscriber;
    }

    private function fakePaystack(string $chargeStatus = 'send_otp')
    {
        Http::fake(function ($request) use ($chargeStatus) {
            $url = $request->url();

            if (str_contains($url, '/charge/submit_otp')) {
                return Http::response([
                    'status' => true,
                    'data' => [
                        'id' => 999,
                        'status' => 'success',
                        'reference' => $request['reference'] ?? null,
                    ],
                ], 200);
            }

            if (str_contains($url, '/charge')) {
                return Http::response([
                    'status' => true,
                    'data' => [
                        'id' => 123,
                        'status' => $chargeStatus,
                        'reference' => $request['reference'] ?? null,
                    ],
                ], 200);
            }

            if (str_contains($url, 'booth_topup.php')) {
                return Http::response(['status' => 'success'], 200);
            }

            if (str_contains($url, 'booth_getbal.php')) {
                return Http::response(['balance' => '17.50'], 200);
            }

            return Http::response([], 200);
        });
    }

    public function test_initiate_sends_a_mobile_money_charge_and_requires_otp(): void
    {
        $this->createSubscriber();
        $this->fakePaystack('send_otp');

        $response = $this->internal()->postJson('/api/internal/ussd/recharge/initiate', [
            'msisdn' => '0249335549',
            'authusername' => '7022875',
            'amount' => 10,
            'network' => 'MTN',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.state', UssdPaymentIntent::STATUS_PENDING)
            ->assertJsonPath('data.requiresOtp', true);

        $reference = $response->json('data.reference');

        $this->assertDatabaseHas('ussd_payment_intents', [
            'reference' => $reference,
            'provider' => 'paystack',
            'requires_otp' => true,
        ]);

        Http::assertSent(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return $path === '/charge'
                && $request['amount'] === 1000
                && $request['currency'] === 'GHS'
                && $request['email'] === 'ama@example.com'
                && $request['mobile_money']['provider'] === 'mtn'
                && $request['mobile_money']['phone'] === '0249335549';
        });
    }

    public function test_telecel_is_charged_as_vodafone(): void
    {
        $this->createSubscriber();
        $this->fakePaystack('pending');

        $this->internal()->postJson('/api/internal/ussd/recharge/initiate', [
            'msisdn' => '0249335549',
            'authusername' => '7022875',
            'amount' => 10,
            'network' => 'TELECEL',
        ])->assertOk();

        Http::assertSent(function ($request) {
            return parse_url($request->url(), PHP_URL_PATH) === '/charge'
                && $request['mobile_money']['provider'] === 'vod';
        });
    }

    public function test_missing_subscriber_email_uses_the_fallback_email(): void
    {
        $this->createSubscriber('SUB_PAY2', '0557466718', '', '3891326');
        $this->fakePaystack('pending');

        $this->internal()->postJson('/api/internal/ussd/recharge/initiate', [
            'msisdn' => '0557466718',
            'authusername' => '3891326',
            'amount' => 10,
        ])->assertOk();

        Http::assertSent(function ($request) {
            return parse_url($request->url(), PHP_URL_PATH) === '/charge'
                && $request['email'] === 'fallback@example.com';
        });
    }

    public function test_submitting_the_otp_completes_the_recharge(): void
    {
        $this->createSubscriber();
        $this->fakePaystack('send_otp');

        $initiate = $this->internal()->postJson('/api/internal/ussd/recharge/initiate', [
            'msisdn' => '0249335549',
            'authusername' => '7022875',
            'amount' => 10,
            'network' => 'MTN',
        ])->assertOk();

        $reference = $initiate->json('data.reference');

        $response = $this->internal()->postJson('/api/internal/ussd/recharge/otp', [
            'reference' => $reference,
            'msisdn' => '0249335549',
            'otp' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.state', UssdPaymentIntent::STATUS_COMPLETED)
            ->assertJsonPath('data.requiresOtp', false);

        $this->assertDatabaseHas('prepaid_credits', [
            'transaction_id' => $reference,
            'authusername' => '7022875',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('ussd_payment_intents', [
            'reference' => $reference,
            'status' => UssdPaymentIntent::STATUS_COMPLETED,
            'requires_otp' => false,
        ]);
    }

    public function test_paystack_webhook_rejects_an_invalid_signature(): void
    {
        $payload = json_encode(['event' => 'charge.success', 'data' => []]);

        $this->call('POST', '/api/webhooks/paystack', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => 'invalid',
        ], $payload)->assertStatus(401);
    }

    public function test_paystack_webhook_credits_once_for_duplicate_deliveries(): void
    {
        $this->createSubscriber();

        $intent = UssdPaymentIntent::create([
            'reference' => 'ALT-WEBHOOK-1',
            'subscriberid' => 'SUB_PAY1',
            'authusername' => '7022875',
            'msisdn' => '233249335549',
            'amount' => 10,
            'network' => 'MTN',
            'provider' => 'paystack',
            'status' => UssdPaymentIntent::STATUS_PENDING,
        ]);

        Http::fake([
            '*/booth_topup.php' => Http::response(['status' => 'success'], 200),
            '*/booth_getbal.php' => Http::response(['balance' => '17.50'], 200),
        ]);

        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'id' => 456,
                'reference' => $intent->reference,
                'amount' => 1000,
                'status' => 'success',
            ],
        ]);

        $signature = hash_hmac('sha512', $payload, self::SECRET);

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
        ];

        $this->call('POST', '/api/webhooks/paystack', [], [], [], $server, $payload)->assertOk();
        $this->call('POST', '/api/webhooks/paystack', [], [], [], $server, $payload)->assertOk();

        $this->assertSame(
            1,
            DB::table('prepaid_credits')->where('transaction_id', $intent->reference)->count()
        );

        $this->assertDatabaseHas('ussd_payment_intents', [
            'reference' => $intent->reference,
            'status' => UssdPaymentIntent::STATUS_COMPLETED,
        ]);
    }

    public function test_paystack_webhook_amount_mismatch_does_not_credit(): void
    {
        $this->createSubscriber();

        $intent = UssdPaymentIntent::create([
            'reference' => 'ALT-WEBHOOK-2',
            'subscriberid' => 'SUB_PAY1',
            'authusername' => '7022875',
            'msisdn' => '233249335549',
            'amount' => 10,
            'network' => 'MTN',
            'provider' => 'paystack',
            'status' => UssdPaymentIntent::STATUS_PENDING,
        ]);

        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'id' => 789,
                'reference' => $intent->reference,
                'amount' => 500,
                'status' => 'success',
            ],
        ]);

        $this->call('POST', '/api/webhooks/paystack', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $payload, self::SECRET),
        ], $payload)->assertOk();

        $this->assertSame(
            0,
            DB::table('prepaid_credits')->where('transaction_id', $intent->reference)->count()
        );

        $this->assertDatabaseHas('ussd_payment_intents', [
            'reference' => $intent->reference,
            'status' => UssdPaymentIntent::STATUS_PENDING,
        ]);
    }

    public function test_a_declined_charge_returns_a_failed_intent_not_a_start_error(): void
    {
        $this->createSubscriber();

        Http::fake([
            '*/charge' => Http::response([
                'status' => false,
                'message' => 'Charge attempted',
                'type' => 'processor_error',
                'code' => 'charge_failed',
                'data' => [
                    'id' => 111,
                    'status' => 'failed',
                    'reference' => 'ALT-DECLINED-1',
                    'gateway_response' => 'Declined. Please use the test mobile money number since you are doing a test transaction.',
                ],
            ], 200),
        ]);

        $response = $this->internal()->postJson('/api/internal/ussd/recharge/initiate', [
            'msisdn' => '0249335549',
            'authusername' => '7022875',
            'amount' => 5,
            'network' => 'MTN',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.state', UssdPaymentIntent::STATUS_FAILED)
            ->assertJsonPath('data.requiresOtp', false);

        $reference = $response->json('data.reference');

        $this->assertDatabaseHas('ussd_payment_intents', [
            'reference' => $reference,
            'status' => UssdPaymentIntent::STATUS_FAILED,
        ]);

        $this->assertStringContainsString(
            'Declined',
            (string) UssdPaymentIntent::where('reference', $reference)->value('failure_reason')
        );

        $this->assertSame(
            0,
            DB::table('prepaid_credits')->where('transaction_id', $reference)->count()
        );
    }
}
