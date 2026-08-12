<?php

namespace Tests\Feature;

use App\Models\SubscribersModel;
use App\Models\SubscribersTempModel;
use App\Models\VoiceRelay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    public function test_v1_register_returns_legacy_envelope(): void
    {
        Http::fake();
        Mail::fake();

        $response = $this->postJson('/api/v1/register', [
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'username' => 'janedoe',
            'phonenumber' => '0241111111',
            'emailaddress' => 'jane@example.com',
            'country' => 'Ghana',
            'password' => 'password123',
            'confirm_password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['status', 'code', 'data', 'message'])
                 ->assertJson(['status' => true]);

        $this->assertDatabaseHas('subscribers_temp', [
            'username' => 'janedoe',
            'fullname' => 'Jane Doe',
            'emailaddress' => 'jane@example.com',
        ]);
    }

    public function test_v1_verify_otp_creates_subscriber_and_provisions(): void
    {
        Http::fake();
        Mail::fake();

        SubscribersTempModel::create([
            'subscriberid' => 'TEMP_V1',
            'fullname' => 'Verify V1',
            'username' => 'vv1',
            'password' => bcrypt('password123'),
            'phonenumber' => '0552222222',
            'emailaddress' => 'vv1@example.com',
            'country' => 'Ghana',
            'otp' => '654321',
            'otp_expiration' => now()->addMinutes(10),
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/v1/verify-otp', [
            'phonenumber' => '0552222222',
            'code' => '654321',
        ]);

        $response->assertStatus(200)
                 ->assertJson(['status' => true]);

        $this->assertDatabaseHas('subscribers', ['username' => 'vv1']);
        $this->assertDatabaseHas('subscriber_auth', [
            'subscriberid' => SubscribersModel::where('username', 'vv1')->value('subscriberid'),
        ]);
        $this->assertDatabaseMissing('subscribers_temp', ['username' => 'vv1']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'passjson.php');
        });
    }

    public function test_v1_login_returns_access_token_in_data(): void
    {
        SubscribersModel::create([
            'subscriberid' => 'SUB_V1LOGIN',
            'fullname' => 'Login V1',
            'username' => 'lv1',
            'password' => bcrypt('password123'),
            'phonenumber' => '0203333333',
            'emailaddress' => 'lv1@example.com',
            'country' => 'Ghana',
            'authusername' => '111222',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'emailaddress' => 'lv1@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['status', 'code', 'data' => ['access_token', 'subscriberid', 'emailaddress'], 'message'])
                 ->assertJson(['status' => true])
                 ->assertJsonMissingPath('data.debug_otp');
    }

    public function test_v1_protected_route_rejects_missing_token_with_legacy_envelope(): void
    {
        $response = $this->getJson('/api/v1/subscriber');

        $response->assertStatus(401)
                 ->assertJsonStructure(['status', 'error' => ['code', 'message'], 'reason'])
                 ->assertJson(['status' => false, 'error' => ['code' => 401]]);
    }

    public function test_v1_token_from_legacy_login_authorizes_current_user_endpoint(): void
    {
        $sub = SubscribersModel::create([
            'subscriberid' => 'SUB_V1TOKEN',
            'fullname' => 'Token V1',
            'username' => 'tv1',
            'password' => bcrypt('password123'),
            'phonenumber' => '0204444444',
            'emailaddress' => 'tv1@example.com',
            'country' => 'Ghana',
        ]);

        $login = $this->postJson('/api/v1/login', ['username' => 'tv1', 'password' => 'password123']);
        $token = $login->json('data.access_token');

        $this->assertNotEmpty($token);

        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->getJson('/api/v1/subscriber');

        $response->assertStatus(200)
                 ->assertJsonPath('data.username', 'tv1');
    }

    public function test_v1_voice_relays_returns_records(): void
    {
        VoiceRelay::create([
            'domain' => 'relay.test',
            'port' => '443',
            'protocol' => 'ws',
            'outboundproxy' => 'proxy.test',
        ]);

        $sub = SubscribersModel::create([
            'subscriberid' => 'SUB_V1RELAY',
            'fullname' => 'Relay V1',
            'username' => 'rv1',
            'password' => bcrypt('password123'),
            'phonenumber' => '0205555555',
            'emailaddress' => 'rv1@example.com',
            'country' => 'Ghana',
        ]);

        $login = $this->postJson('/api/v1/login', ['username' => 'rv1', 'password' => 'password123']);
        $token = $login->json('data.access_token');

        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->getJson('/api/v1/voice-relays');

        $response->assertStatus(200)
                 ->assertJson(['status' => true])
                 ->assertJsonPath('data.0.domain', 'relay.test');
    }

    public function test_v1_unknown_endpoint_returns_legacy_404_envelope(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $response->assertStatus(404)
                 ->assertJson([
                     'status' => false,
                     'error' => ['code' => 404, 'message' => 'End Point Not Found'],
                 ]);
    }

    public function test_v1_check_balance_passthroughs_external_response(): void
    {
        SubscribersModel::create([
            'subscriberid' => 'SUB_V1BAL',
            'fullname' => 'Balance V1',
            'username' => 'bv1',
            'password' => bcrypt('password123'),
            'phonenumber' => '0206666666',
            'emailaddress' => 'bv1@example.com',
            'country' => 'Ghana',
            'authusername' => '999001',
        ]);

        \App\Models\SubscriberAuthModel::create([
            'subscriberid' => 'SUB_V1BAL',
            'authusername' => '999001',
            'authpassword' => 'secret',
            'status' => 'active',
        ]);

        Http::fake([
            '*/booth_getbal.php' => Http::response(['status' => 'success', 'balance' => '42.50'], 200),
        ]);

        $login = $this->postJson('/api/v1/login', ['username' => 'bv1', 'password' => 'password123']);
        $token = $login->json('data.access_token');

        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->postJson('/api/v1/check-balance', ['authusername' => '999001']);

        $response->assertStatus(200)
                 ->assertJson(['status' => true])
                 ->assertJsonPath('data.balance', '42.50');
    }

    public function test_v1_payment_link_returns_web_checkout_url_for_owned_account(): void
    {
        SubscribersModel::create([
            'subscriberid' => 'SUB_V1LINK',
            'fullname' => 'Link V1',
            'username' => 'lk1',
            'password' => bcrypt('password123'),
            'phonenumber' => '0207777777',
            'emailaddress' => 'lk1@example.com',
            'country' => 'Ghana',
        ]);

        \App\Models\SubscriberAuthModel::create([
            'subscriberid' => 'SUB_V1LINK',
            'authusername' => '777001',
            'authpassword' => 'secret',
            'status' => 'active',
        ]);

        $login = $this->postJson('/api/v1/login', ['username' => 'lk1', 'password' => 'password123']);
        $token = $login->json('data.access_token');

        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->postJson('/api/v1/payment-link', [
                             'authusername' => '777001',
                             'amount' => 50,
                         ]);

        $response->assertStatus(200)
                 ->assertJson(['status' => true])
                 ->assertJsonPath('data.url', rtrim(config('app.frontend_url'), '/') . '/dashboard.html?buy=1&authusername=777001&amount=50');
    }

    public function test_v1_payment_link_rejects_foreign_account(): void
    {
        SubscribersModel::create([
            'subscriberid' => 'SUB_V1LNKOWN',
            'fullname' => 'Owner V1',
            'username' => 'own1',
            'password' => bcrypt('password123'),
            'phonenumber' => '0208888888',
            'emailaddress' => 'own1@example.com',
            'country' => 'Ghana',
        ]);

        \App\Models\SubscriberAuthModel::create([
            'subscriberid' => 'SUB_V1LNKOWN',
            'authusername' => '888001',
            'authpassword' => 'secret',
            'status' => 'active',
        ]);

        SubscribersModel::create([
            'subscriberid' => 'SUB_V1LNKX',
            'fullname' => 'Other V1',
            'username' => 'oth1',
            'password' => bcrypt('password123'),
            'phonenumber' => '0209999999',
            'emailaddress' => 'oth1@example.com',
            'country' => 'Ghana',
        ]);

        \App\Models\SubscriberAuthModel::create([
            'subscriberid' => 'SUB_V1LNKX',
            'authusername' => '999001',
            'authpassword' => 'secret',
            'status' => 'active',
        ]);

        $login = $this->postJson('/api/v1/login', ['username' => 'own1', 'password' => 'password123']);
        $token = $login->json('data.access_token');

        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->postJson('/api/v1/payment-link', [
                             'authusername' => '999001',
                             'amount' => 50,
                         ]);

        $response->assertStatus(403)
                 ->assertJson(['status' => false])
                 ->assertJsonPath('error.code', 403);
    }
}