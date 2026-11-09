<?php

namespace Tests\Feature;

use App\Http\Controllers\SubscribersController;
use App\Models\PrepaidCredit;
use App\Models\SubscriberAuthModel;
use App\Models\SubscribersModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebPaymentCreditTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_web_credit';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paystack.secret_key' => self::SECRET,
            'services.paystack.fallback_email' => 'fallback@example.com',
        ]);
    }

    private function subscriber(): SubscribersModel
    {
        $subscriber = SubscribersModel::create([
            'subscriberid' => 'SUB_WEB1',
            'fullname' => 'Web Test',
            'username' => 'webtest',
            'password' => bcrypt('secret123'),
            'phonenumber' => '0249335549',
            'emailaddress' => 'web@example.com',
            'country' => 'Ghana',
            'authusername' => '7022875',
            'switch_status' => 'active',
            'billing_acc_status' => 'active',
        ]);

        SubscriberAuthModel::create([
            'subscriberid' => 'SUB_WEB1',
            'authusername' => '7022875',
            'authpassword' => 'secret',
            'status' => 'active',
        ]);

        return $subscriber;
    }

    private function fakeStack(string $reference, bool $topupFails = false, ?int &$topups = null): void
    {
        $topups = 0;

        Http::fake(function ($request) use ($reference, $topupFails, &$topups) {
            $url = $request->url();

            if (str_contains($url, 'transaction/verify/' . $reference)) {
                return Http::response([
                    'status' => true,
                    'data' => [
                        'status' => 'success',
                        'reference' => $reference,
                        'amount' => 1000,
                        'metadata' => [
                            'authusername' => '7022875',
                            'subscriberid' => 'SUB_WEB1',
                            'source' => 'web',
                        ],
                    ],
                ], 200);
            }

            if (str_contains($url, 'booth_topup.php')) {
                $topups++;

                return $topupFails
                    ? Http::response('', 500)
                    : Http::response(['status' => 'success'], 200);
            }

            if (str_contains($url, 'booth_getbal.php')) {
                return Http::response(['balance' => '17.50'], 200);
            }

            return Http::response([], 200);
        });
    }

    public function test_web_verify_credits_once_and_is_idempotent(): void
    {
        $this->subscriber();
        $this->fakeStack('REF-WEB-1', false, $topups);

        $first = $this->postJson('/api/payments/verify', ['reference' => 'REF-WEB-1']);
        $first->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('pending', false)
            ->assertJsonPath('credit_status', PrepaidCredit::STATUS_COMPLETED);

        $this->postJson('/api/payments/verify', ['reference' => 'REF-WEB-1'])->assertOk();

        $this->assertSame(1, $topups);
        $this->assertDatabaseCount('prepaid_credits', 1);
        $this->assertDatabaseHas('prepaid_credits', [
            'transaction_id' => 'REF-WEB-1',
            'status' => PrepaidCredit::STATUS_COMPLETED,
            'source' => 'web',
        ]);
    }

    public function test_web_verify_reports_pending_when_booth_fails(): void
    {
        $this->subscriber();
        $this->fakeStack('REF-WEB-2', true, $topups);

        $this->postJson('/api/payments/verify', ['reference' => 'REF-WEB-2'])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('pending', true)
            ->assertJsonPath('credit_status', PrepaidCredit::STATUS_PENDING_TOPUP);

        $this->assertDatabaseHas('prepaid_credits', [
            'transaction_id' => 'REF-WEB-2',
            'status' => PrepaidCredit::STATUS_PENDING_TOPUP,
        ]);
    }

    public function test_paystack_webhook_credits_web_payments_as_a_safety_net(): void
    {
        $this->subscriber();
        $this->fakeStack('REF-HOOK-1', false, $topups);

        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'id' => 555,
                'reference' => 'REF-HOOK-1',
                'amount' => 1000,
                'metadata' => [
                    'authusername' => '7022875',
                    'subscriberid' => 'SUB_WEB1',
                    'source' => 'web',
                ],
            ],
        ]);

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $payload, self::SECRET),
        ];

        $this->call('POST', '/api/webhooks/paystack', [], [], [], $server, $payload)->assertOk();
        $this->call('POST', '/api/webhooks/paystack', [], [], [], $server, $payload)->assertOk();

        $this->assertSame(1, $topups);
        $this->assertDatabaseHas('prepaid_credits', [
            'transaction_id' => 'REF-HOOK-1',
            'status' => PrepaidCredit::STATUS_COMPLETED,
            'source' => 'web',
        ]);
    }

    public function test_purchase_history_splits_completed_pending_and_failed(): void
    {
        $subscriber = $this->subscriber();

        foreach ([
            ['PAY-COMPLETED', PrepaidCredit::STATUS_COMPLETED],
            ['PAY-PENDING', PrepaidCredit::STATUS_PENDING_TOPUP],
            ['PAY-FAILED', PrepaidCredit::STATUS_FAILED],
        ] as [$reference, $status]) {
            PrepaidCredit::create([
                'authusername' => '7022875',
                'amount' => 10,
                'transaction_id' => $reference,
                'status' => $status,
                'source' => 'web',
                'paid_at' => now(),
            ]);
        }

        $controller = app(SubscribersController::class);

        $completed = $this->history($controller, $subscriber, 'getPurchaseHistory');
        $pending = $this->history($controller, $subscriber, 'getPendingPurchaseHistory');
        $failed = $this->history($controller, $subscriber, 'getFailedPurchaseHistory');

        $this->assertSame(['PAY-COMPLETED'], array_column($completed, 'transaction_id'));
        $this->assertSame(['PAY-PENDING'], array_column($pending, 'transaction_id'));
        $this->assertSame(['PAY-FAILED'], array_column($failed, 'transaction_id'));
    }

    private function history(SubscribersController $controller, SubscribersModel $subscriber, string $method): array
    {
        $request = Request::create('/purchase-history', 'GET');
        $request->setUserResolver(fn () => $subscriber);

        $response = $controller->{$method}($request);

        return $response->getData(true)['data'];
    }
}
