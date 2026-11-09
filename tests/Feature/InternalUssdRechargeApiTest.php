<?php

namespace Tests\Feature;

use App\Models\PrepaidCredit;
use App\Models\SubscriberAuthModel;
use App\Models\SubscribersModel;
use App\Models\UssdPaymentIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class InternalUssdRechargeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ussd.internal_token' => 'test-internal-token',
            'services.ussd.internal_ip_allowlist' => [],
            'services.ussd.recharge_enabled' => true,
            'services.ussd.recharge_min' => 1,
            'services.ussd.recharge_max' => 500,
            'services.ussd.recharge_rate_limit_max' => 5,
            'services.ussd.recharge_rate_limit_window_seconds' => 3600,
            'services.ussd.payment_provider' => 'fake',
            'services.ussd.fake_payment_outcome' => 'success',
            'services.ussd.fake_payment_delay_seconds' => 0,
        ]);

        Queue::fake();
        RateLimiter::clear('ussd-recharge:233249335549');
    }

    private int $topupStatus = 200;

    private function internal()
    {
        return $this->withHeader('X-Internal-Token', 'test-internal-token');
    }

    private function createSubscriber(
        string $subscriberid = 'SUB_USSD1',
        string $phonenumber = '0249335549',
        string $defaultAuth = '7022875',
        string $billingStatus = 'active',
    ): SubscribersModel {
        return SubscribersModel::create([
            'subscriberid' => $subscriberid,
            'fullname' => 'Ama Mensah',
            'username' => strtolower($subscriberid),
            'password' => bcrypt('secret123'),
            'phonenumber' => $phonenumber,
            'emailaddress' => strtolower($subscriberid) . '@example.com',
            'country' => 'Ghana',
            'authusername' => $defaultAuth,
            'switch_status' => 'active',
            'billing_acc_status' => $billingStatus,
        ]);
    }

    private function createAccount(string $subscriberid, string $authusername, string $status = 'active'): SubscriberAuthModel
    {
        return SubscriberAuthModel::create([
            'subscriberid' => $subscriberid,
            'authusername' => $authusername,
            'authpassword' => 'secret',
            'status' => $status,
        ]);
    }

    private function fakeBooth(int $topupStatus = 200, float $balance = 12.5): void
    {
        $this->topupStatus = $topupStatus;

        Http::fake(function ($request) use ($balance) {
            if (str_contains($request->url(), 'booth_topup.php')) {
                $status = $this->topupStatus;

                return Http::response(['status' => $status === 200 ? 'success' : 'error'], $status);
            }

            return Http::response(['status' => 'success', 'balance' => (string) $balance], 200);
        });
    }

    private function initiate(string $msisdn = '0249335549', string $auth = '7022875', float $amount = 10)
    {
        return $this->internal()->postJson('/api/internal/ussd/recharge/initiate', [
            'msisdn' => $msisdn,
            'authusername' => $auth,
            'amount' => $amount,
            'network' => 'MTN',
        ]);
    }

    public function test_recharge_requires_the_internal_token(): void
    {
        $this->postJson('/api/internal/ussd/recharge/initiate', [
            'msisdn' => '0249335549',
            'authusername' => '7022875',
            'amount' => 10,
        ])->assertStatus(401);
    }

    public function test_initiate_completes_immediately_and_credits_the_sip(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '7022875');
        $this->fakeBooth();

        $response = $this->initiate();

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.state', UssdPaymentIntent::STATUS_COMPLETED)
            ->assertJsonPath('data.message', 'Payment successful.')
            ->assertJsonPath('data.balance', 12.5);

        $reference = $response->json('data.reference');

        $this->assertDatabaseHas('ussd_payment_intents', [
            'reference' => $reference,
            'authusername' => '7022875',
            'amount' => 10,
            'status' => UssdPaymentIntent::STATUS_COMPLETED,
        ]);

        $this->assertDatabaseHas('prepaid_credits', [
            'transaction_id' => $reference,
            'authusername' => '7022875',
            'amount' => 10,
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'booth_topup.php')
                && $request['accesscode'] === '7022875'
                && (float) $request['amount'] === 10.0;
        });
    }

    public function test_initiate_is_idempotent_for_the_same_pending_payment(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '7022875');
        config(['services.ussd.fake_payment_delay_seconds' => 60]);

        $first = $this->initiate()->assertOk()->assertJsonPath('data.state', UssdPaymentIntent::STATUS_PENDING);
        $second = $this->initiate()->assertOk()->assertJsonPath('data.state', UssdPaymentIntent::STATUS_PENDING);

        $this->assertSame($first->json('data.reference'), $second->json('data.reference'));
        $this->assertDatabaseCount('ussd_payment_intents', 1);
    }

    public function test_status_returns_pending_until_the_provider_completes(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '7022875');
        $this->fakeBooth();
        config(['services.ussd.fake_payment_delay_seconds' => 60]);

        $reference = $this->initiate()->json('data.reference');

        $this->internal()
            ->getJson("/api/internal/ussd/recharge/status/{$reference}?msisdn=0249335549")
            ->assertOk()
            ->assertJsonPath('data.state', UssdPaymentIntent::STATUS_PENDING);

        config(['services.ussd.fake_payment_delay_seconds' => 0]);

        $this->internal()
            ->getJson("/api/internal/ussd/recharge/status/{$reference}?msisdn=0249335549")
            ->assertOk()
            ->assertJsonPath('data.state', UssdPaymentIntent::STATUS_COMPLETED)
            ->assertJsonPath('data.transactionId', $reference)
            ->assertJsonPath('data.balance', 12.5);

        $this->assertDatabaseHas('prepaid_credits', [
            'transaction_id' => $reference,
            'amount' => 10,
        ]);
    }

    public function test_status_reports_a_failed_payment_without_crediting_the_sip(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '7022875');
        $this->fakeBooth();
        config(['services.ussd.fake_payment_delay_seconds' => 60]);

        $reference = $this->initiate()->json('data.reference');

        config([
            'services.ussd.fake_payment_delay_seconds' => 0,
            'services.ussd.fake_payment_outcome' => 'failed',
        ]);

        $this->internal()
            ->getJson("/api/internal/ussd/recharge/status/{$reference}?msisdn=0249335549")
            ->assertOk()
            ->assertJsonPath('data.state', UssdPaymentIntent::STATUS_FAILED)
            ->assertJsonPath('data.message', 'Payment failed or was cancelled.');

        $this->assertDatabaseCount('prepaid_credits', 0);
    }

    public function test_status_hides_payments_that_belong_to_another_number(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '7022875');
        config(['services.ussd.fake_payment_delay_seconds' => 60]);

        $reference = $this->initiate()->json('data.reference');

        $this->internal()
            ->getJson("/api/internal/ussd/recharge/status/{$reference}?msisdn=0557466718")
            ->assertStatus(404)
            ->assertJson(['status' => false, 'code' => 'NOT_FOUND']);
    }

    public function test_confirm_rejects_a_mismatched_amount(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '7022875');
        $this->fakeBooth();
        config(['services.ussd.fake_payment_delay_seconds' => 60]);

        $reference = $this->initiate()->json('data.reference');

        $this->internal()->postJson('/api/internal/ussd/recharge/confirm', [
            'reference' => $reference,
            'externalRef' => 'FAKE-123',
            'paidAmount' => 5,
            'providerStatus' => 'success',
        ])->assertStatus(409)
            ->assertJson(['status' => false, 'code' => 'AMOUNT_MISMATCH']);

        $this->assertDatabaseCount('prepaid_credits', 0);
    }

    public function test_confirm_marks_a_failed_payment(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '7022875');
        $this->fakeBooth();
        config(['services.ussd.fake_payment_delay_seconds' => 60]);

        $reference = $this->initiate()->json('data.reference');

        $this->internal()->postJson('/api/internal/ussd/recharge/confirm', [
            'reference' => $reference,
            'paidAmount' => 10,
            'providerStatus' => 'failed',
        ])->assertOk()
            ->assertJsonPath('data.state', UssdPaymentIntent::STATUS_FAILED);

        $this->assertDatabaseCount('prepaid_credits', 0);
    }

    public function test_confirm_keeps_the_payment_pending_when_booth_topup_fails_then_retries(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '7022875');
        $this->fakeBooth(500);
        config(['services.ussd.fake_payment_delay_seconds' => 60]);

        $reference = $this->initiate()->json('data.reference');

        $this->internal()->postJson('/api/internal/ussd/recharge/confirm', [
            'reference' => $reference,
            'paidAmount' => 10,
            'providerStatus' => 'success',
        ])->assertOk()
            ->assertJsonPath('data.state', UssdPaymentIntent::STATUS_PENDING_TOPUP)
            ->assertJsonPath('data.message', 'Payment received. Credit is being applied.');

        $this->assertDatabaseHas('prepaid_credits', [
            'transaction_id' => $reference,
            'amount' => 10,
        ]);

        $this->fakeBooth(200);

        $this->internal()->postJson('/api/internal/ussd/recharge/confirm', [
            'reference' => $reference,
            'paidAmount' => 10,
            'providerStatus' => 'success',
        ])->assertOk()
            ->assertJsonPath('data.state', UssdPaymentIntent::STATUS_COMPLETED);

        $this->assertDatabaseHas('ussd_payment_intents', [
            'reference' => $reference,
            'status' => UssdPaymentIntent::STATUS_COMPLETED,
            'topup_attempts' => 2,
        ]);
    }

    public function test_duplicate_confirm_creates_exactly_one_credit_and_one_topup(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '7022875');
        $this->fakeBooth();
        config(['services.ussd.fake_payment_delay_seconds' => 60]);

        $reference = $this->initiate()->json('data.reference');

        $payload = [
            'reference' => $reference,
            'externalRef' => 'FAKE-123',
            'paidAmount' => 10,
            'providerStatus' => 'success',
        ];

        $this->internal()->postJson('/api/internal/ussd/recharge/confirm', $payload)
            ->assertOk()
            ->assertJsonPath('data.state', UssdPaymentIntent::STATUS_COMPLETED);

        $this->internal()->postJson('/api/internal/ussd/recharge/confirm', $payload)
            ->assertOk()
            ->assertJsonPath('data.state', UssdPaymentIntent::STATUS_COMPLETED);

        $this->assertDatabaseCount('prepaid_credits', 1);
        $this->assertSame(1, Http::recorded(
            fn ($request) => str_contains($request->url(), 'booth_topup.php'),
        )->count());
    }

    public function test_disable_switch_blocks_new_recharges_but_still_settles_existing_ones(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '7022875');
        $this->fakeBooth();
        config(['services.ussd.fake_payment_delay_seconds' => 60]);

        $reference = $this->initiate()->json('data.reference');

        config(['services.ussd.recharge_enabled' => false]);

        $this->initiate()
            ->assertStatus(503)
            ->assertJson(['status' => false, 'code' => 'RECHARGE_UNAVAILABLE']);

        $this->assertDatabaseCount('ussd_payment_intents', 1);

        config(['services.ussd.fake_payment_delay_seconds' => 0]);

        $this->internal()
            ->getJson("/api/internal/ussd/recharge/status/{$reference}?msisdn=0249335549")
            ->assertOk()
            ->assertJsonPath('data.state', UssdPaymentIntent::STATUS_COMPLETED);
    }

    public function test_fake_provider_cannot_take_new_recharges_in_production(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '7022875');
        $this->app['env'] = 'production';
        config([
            'services.ussd.recharge_enabled' => true,
            'services.ussd.payment_provider' => 'fake',
        ]);

        $this->initiate()
            ->assertStatus(503)
            ->assertJson(['status' => false, 'code' => 'RECHARGE_UNAVAILABLE']);

        $this->assertDatabaseCount('ussd_payment_intents', 0);
        $this->assertDatabaseCount('prepaid_credits', 0);
    }

    public function test_initiate_is_rate_limited_per_msisdn(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '7022875');
        $this->fakeBooth();
        config([
            'services.ussd.fake_payment_delay_seconds' => 60,
            'services.ussd.recharge_rate_limit_max' => 2,
        ]);

        $this->initiate()->assertOk();
        $this->initiate()->assertOk();

        $this->initiate()
            ->assertStatus(429)
            ->assertJson(['status' => false, 'code' => 'RATE_LIMITED'])
            ->assertHeader('Retry-After');

        $this->assertDatabaseCount('ussd_payment_intents', 1);
    }

    public function test_initiate_rejects_an_amount_outside_the_configured_range(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '7022875');

        $this->initiate(amount: 0.5)
            ->assertStatus(422)
            ->assertJson(['status' => false, 'code' => 'INVALID_AMOUNT']);

        $this->initiate(amount: 501)
            ->assertStatus(422)
            ->assertJson(['status' => false, 'code' => 'INVALID_AMOUNT']);
    }

    public function test_initiate_rejects_an_account_owned_by_someone_else(): void
    {
        $this->createSubscriber();
        $this->createSubscriber('SUB_OTHER', '0557466718', '999001');
        $this->createAccount('SUB_OTHER', '999001');

        $this->initiate(auth: '999001')
            ->assertStatus(403)
            ->assertJson(['status' => false, 'code' => 'FORBIDDEN']);
    }

    public function test_initiate_rejects_an_inactive_subscriber(): void
    {
        $this->createSubscriber(billingStatus: 'suspended');
        $this->createAccount('SUB_USSD1', '7022875');

        $this->initiate()
            ->assertStatus(403)
            ->assertJson(['status' => false, 'code' => 'ACCOUNT_INACTIVE']);
    }

    public function test_initiate_reports_a_failed_fake_payment(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '7022875');
        config(['services.ussd.fake_payment_outcome' => 'failed']);

        $this->initiate()
            ->assertOk()
            ->assertJsonPath('data.state', UssdPaymentIntent::STATUS_FAILED)
            ->assertJsonPath('data.requiresOtp', false);

        $this->assertDatabaseHas('ussd_payment_intents', [
            'status' => UssdPaymentIntent::STATUS_FAILED,
        ]);
        $this->assertDatabaseCount('prepaid_credits', 0);
    }
}
