<?php

namespace Tests\Feature;

use App\Models\SubscriberAuthModel;
use App\Models\SubscribersModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InternalUssdApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ussd.internal_token' => 'test-internal-token',
            'services.ussd.internal_ip_allowlist' => [],
        ]);
    }

    private function internal()
    {
        return $this->withHeader('X-Internal-Token', 'test-internal-token');
    }

    private function createSubscriber(
        string $subscriberid = 'SUB_USSD1',
        string $phonenumber = '0249335549',
        ?string $defaultAuth = '7022875',
        string $fullname = 'Ama Mensah'
    ): SubscribersModel {
        return SubscribersModel::create([
            'subscriberid' => $subscriberid,
            'fullname' => $fullname,
            'username' => strtolower($subscriberid),
            'password' => bcrypt('secret123'),
            'phonenumber' => $phonenumber,
            'emailaddress' => strtolower($subscriberid) . '@example.com',
            'country' => 'Ghana',
            'authusername' => $defaultAuth,
            'switch_status' => 'active',
            'billing_acc_status' => 'active',
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

    public function test_internal_routes_require_a_token(): void
    {
        $this->postJson('/api/internal/ussd/lookup', ['msisdn' => '0249335549'])
            ->assertStatus(401)
            ->assertJson(['status' => false, 'code' => 'UNAUTHORIZED']);
    }

    public function test_internal_routes_preserve_the_request_id(): void
    {
        $this->createSubscriber();

        $this->internal()
            ->withHeader('X-Request-Id', 'trace-123')
            ->postJson('/api/internal/ussd/lookup', ['msisdn' => '0249335549'])
            ->assertOk()
            ->assertHeader('X-Request-Id', 'trace-123');
    }

    public function test_lookup_returns_subscriber_and_accounts(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '3891326');
        $this->createAccount('SUB_USSD1', '7022875');

        $response = $this->internal()->postJson('/api/internal/ussd/lookup', [
            'msisdn' => '233249335549',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.registered', true)
            ->assertJsonPath('data.subscriber.subscriberid', 'SUB_USSD1')
            ->assertJsonPath('data.subscriber.defaultAuthusername', '7022875')
            ->assertJsonPath('data.duplicateMatches', 0)
            ->assertJsonCount(2, 'data.accounts')
            ->assertJsonPath('data.accounts.0.authusername', '7022875')
            ->assertJsonPath('data.accounts.0.isDefault', true)
            ->assertJsonPath('data.accounts.1.authusername', '3891326');
    }

    public function test_lookup_accepts_local_and_plus_prefixed_numbers(): void
    {
        $this->createSubscriber();

        $this->internal()->postJson('/api/internal/ussd/lookup', ['msisdn' => '0249335549'])
            ->assertOk()
            ->assertJsonPath('data.registered', true);

        $this->internal()->postJson('/api/internal/ussd/lookup', ['msisdn' => '+233249335549'])
            ->assertOk()
            ->assertJsonPath('data.registered', true);
    }

    public function test_lookup_reports_unknown_numbers_without_404(): void
    {
        $response = $this->internal()->postJson('/api/internal/ussd/lookup', [
            'msisdn' => '0240000000',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.registered', false)
            ->assertJsonCount(0, 'data.accounts');
    }

    public function test_lookup_uses_the_latest_subscriber_when_the_number_is_duplicated(): void
    {
        $this->createSubscriber('SUB_OLD', '0249335549', '7022875', 'Old User');

        $this->createSubscriber('SUB_NEW', '0249335549', '7022875', 'New User');

        DB::table('subscribers')
            ->where('subscriberid', 'SUB_NEW')
            ->update(['regdatetime' => now()->addMinute()]);

        $response = $this->internal()->postJson('/api/internal/ussd/lookup', [
            'msisdn' => '0249335549',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.subscriber.subscriberid', 'SUB_NEW')
            ->assertJsonPath('data.duplicateMatches', 1);
    }

    public function test_accounts_returns_balances_when_requested(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '3891326');
        $this->createAccount('SUB_USSD1', '7022875');

        Http::fake(function ($request) {
            $accesscode = $request['accesscode'] ?? null;

            return Http::response([
                'status' => 'success',
                'balance' => $accesscode === '7022875' ? '12.50' : '0.00',
            ], 200);
        });

        $response = $this->internal()->postJson('/api/internal/ussd/accounts', [
            'msisdn' => '0249335549',
            'withBalance' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.accounts.0.authusername', '7022875')
            ->assertJsonPath('data.accounts.0.balance', 12.5)
            ->assertJsonPath('data.accounts.1.authusername', '3891326')
            ->assertJsonPath('data.accounts.1.balance', 0);
    }

    public function test_accounts_returns_null_balance_when_booth_fails(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '7022875');

        Http::fake(function () {
            return Http::response('', 500);
        });

        $response = $this->internal()->postJson('/api/internal/ussd/accounts', [
            'msisdn' => '0249335549',
            'withBalance' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.accounts.0.balance', null);
    }

    public function test_balance_returns_the_booth_balance(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '7022875');

        Http::fake([
            '*/booth_getbal.php' => Http::response(['status' => 'success', 'balance' => '42.50'], 200),
        ]);

        $response = $this->internal()->postJson('/api/internal/ussd/balance', [
            'msisdn' => '0249335549',
            'authusername' => '7022875',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.authusername', '7022875')
            ->assertJsonPath('data.balance', 42.5);
    }

    public function test_balance_rejects_an_account_owned_by_someone_else(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '7022875');

        $this->createSubscriber('SUB_OTHER', '0557466718', '999001');
        $this->createAccount('SUB_OTHER', '999001');

        $response = $this->internal()->postJson('/api/internal/ussd/balance', [
            'msisdn' => '0249335549',
            'authusername' => '999001',
        ]);

        $response->assertStatus(403)
            ->assertJson(['status' => false, 'code' => 'FORBIDDEN']);
    }

    public function test_balance_returns_502_when_booth_fails(): void
    {
        $this->createSubscriber();
        $this->createAccount('SUB_USSD1', '7022875');

        Http::fake([
            '*/booth_getbal.php' => Http::response('', 500),
        ]);

        $response = $this->internal()->postJson('/api/internal/ussd/balance', [
            'msisdn' => '0249335549',
            'authusername' => '7022875',
        ]);

        $response->assertStatus(502)
            ->assertJson(['status' => false, 'code' => 'BOOTH_UNAVAILABLE']);
    }

    public function test_accounts_returns_404_for_unknown_numbers(): void
    {
        $response = $this->internal()->postJson('/api/internal/ussd/accounts', [
            'msisdn' => '0240000000',
        ]);

        $response->assertStatus(404)
            ->assertJson(['status' => false, 'code' => 'NOT_REGISTERED']);
    }

    public function test_existing_api_routes_still_work(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertStatus(404)
            ->assertJson([
                'status' => false,
                'error' => ['code' => 404],
            ]);

        $this->getJson('/api/user')->assertStatus(401);
    }
}
