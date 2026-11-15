<?php

namespace Tests\Feature;

use App\Models\AdminModel;
use App\Models\Country;
use App\Models\SubscriberAuthModel;
use App\Models\SubscriberChannelModel;
use App\Models\SubscribersModel;
use App\Models\SubscribersTempModel;
use App\Services\UssdSubscriberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CountryChannelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): AdminModel
    {
        return AdminModel::create([
            'name' => 'Channel Admin',
            'username' => 'channeladmin',
            'email' => 'channeladmin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'manager',
        ]);
    }

    private function mobileAccount(string $username = 'mobileuser', string $phone = '0241111000'): SubscribersModel
    {
        Http::fake();
        Mail::fake();

        $this->postJson('/api/v1/register', [
            'firstname' => 'Mobile',
            'lastname' => 'User',
            'username' => $username,
            'password' => 'password123',
            'confirm_password' => 'password123',
            'phonenumber' => $phone,
            'emailaddress' => $username . '@example.com',
            'country' => 'Ghana',
        ])->assertOk();

        $otp = SubscribersTempModel::where('username', $username)->value('otp');

        $this->postJson('/api/v1/verify-otp', [
            'phonenumber' => $phone,
            'code' => $otp,
        ])->assertOk();

        return SubscribersModel::where('username', $username)->firstOrFail();
    }

    private function boothAccount(string $username = 'boothuser', string $phone = '0242222000'): SubscribersModel
    {
        Http::fake();
        Mail::fake();

        $signup = $this->postJson('/api/signup', [
            'fullname' => 'Booth User',
            'username' => $username,
            'password' => 'password123',
            'confirm_password' => 'password123',
            'phonenumber' => $phone,
            'emailaddress' => $username . '@example.com',
        ])->assertCreated()->json();

        $this->postJson('/api/verify', [
            'phonenumber' => $phone,
            'otp' => $signup['debug_otp'],
        ])->assertOk();

        return SubscribersModel::where('username', $username)->firstOrFail();
    }

    public function test_public_country_endpoint_returns_only_active_countries_ordered(): void
    {
        Country::where('name', 'Nigeria')->update(['is_active' => false]);

        $response = $this->getJson('/api/v1/countries')->assertOk();

        $response->assertJsonPath('status', true);
        $this->assertNotEmpty($response->json('data'));

        $names = collect($response->json('data'))->pluck('name');
        $this->assertFalse($names->contains('Nigeria'));
        $this->assertTrue($names->contains('Ghana'));
    }

    public function test_mobile_signup_creates_mobile_channel_with_country_domain(): void
    {
        $subscriber = $this->mobileAccount();

        $this->assertTrue($subscriber->has_mobile_account);
        $this->assertFalse($subscriber->has_booth_account);

        $this->assertDatabaseHas('subscriber_channels', [
            'subscriberid' => $subscriber->subscriberid,
            'channel' => 'mobile',
            'domain' => 'ghana.altfon.net',
        ]);

        $this->assertDatabaseHas('subscriber_auth', [
            'subscriberid' => $subscriber->subscriberid,
            'channel' => 'mobile',
        ]);

        $auth = SubscriberAuthModel::where('subscriberid', $subscriber->subscriberid)->firstOrFail();
        $this->assertSame(SubscriberAuthModel::PASSWORD_LENGTH, strlen($auth->authpassword));
    }

    public function test_booth_signup_creates_booth_channel_without_country(): void
    {
        $subscriber = $this->boothAccount();

        $this->assertFalse($subscriber->has_mobile_account);
        $this->assertTrue($subscriber->has_booth_account);
        $this->assertNull($subscriber->country_id);

        $this->assertDatabaseHas('subscriber_channels', [
            'subscriberid' => $subscriber->subscriberid,
            'channel' => 'booth',
            'domain' => 'booth.altfon.net',
        ]);
    }

    public function test_booth_login_prompts_for_mobile_only_account_and_channel_creation_links_it(): void
    {
        $subscriber = $this->mobileAccount();

        $login = $this->postJson('/api/login', [
            'username' => $subscriber->username,
            'password' => 'password123',
        ]);

        $login->assertStatus(403)
            ->assertJsonPath('error.reason', 'booth_account_required');

        $setupToken = $login->json('error.details.setup_token');
        $this->assertNotEmpty($setupToken);

        $created = $this->withHeader('Authorization', 'Bearer ' . $setupToken)
            ->postJson('/api/account-channels', ['channel' => 'booth'])
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertNotEmpty($created->json('token'));

        $subscriber->refresh();
        $this->assertTrue($subscriber->has_booth_account);
        $this->assertDatabaseHas('subscriber_channels', [
            'subscriberid' => $subscriber->subscriberid,
            'channel' => 'booth',
            'domain' => 'booth.altfon.net',
        ]);
    }

    public function test_mobile_login_prompts_for_booth_only_account_and_channel_creation_links_it(): void
    {
        $subscriber = $this->boothAccount();
        $ghana = Country::where('name', 'Ghana')->firstOrFail();

        $login = $this->postJson('/api/v1/login', [
            'username' => $subscriber->username,
            'password' => 'password123',
        ]);

        $login->assertStatus(403)
            ->assertJsonPath('error.reason', 'mobile_account_required');

        $setupToken = $login->json('error.details.setup_token');
        $this->assertNotEmpty($setupToken);

        $created = $this->withHeader('Authorization', 'Bearer ' . $setupToken)
            ->postJson('/api/v1/account-channels', [
                'channel' => 'mobile',
                'country_id' => $ghana->id,
            ])
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertNotEmpty($created->json('data.access_token'));

        $subscriber->refresh();
        $this->assertTrue($subscriber->has_mobile_account);
        $this->assertDatabaseHas('subscriber_channels', [
            'subscriberid' => $subscriber->subscriberid,
            'channel' => 'mobile',
            'domain' => 'ghana.altfon.net',
        ]);
    }

    public function test_admin_can_create_a_subscriber_with_both_channels(): void
    {
        Http::fake();
        $admin = $this->admin();
        $ghana = Country::where('name', 'Ghana')->firstOrFail();

        $response = $this->actingAs($admin, 'admin-api')->postJson('/api/admin/subscribers', [
            'fullname' => 'Both User',
            'username' => 'bothuser',
            'emailaddress' => 'bothuser@example.com',
            'phonenumber' => '0243333000',
            'password' => 'password123',
            'country_id' => $ghana->id,
            'has_mobile_account' => true,
            'has_booth_account' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.has_mobile_account', true)
            ->assertJsonPath('data.has_booth_account', true);

        $subscriber = SubscribersModel::where('username', 'bothuser')->firstOrFail();

        $this->assertSame(2, $subscriber->channels()->count());
        $this->assertSame(2, SubscriberAuthModel::where('subscriberid', $subscriber->subscriberid)->count());

        $this->assertDatabaseHas('subscriber_channels', [
            'subscriberid' => $subscriber->subscriberid,
            'channel' => 'mobile',
            'domain' => 'ghana.altfon.net',
        ]);
        $this->assertDatabaseHas('subscriber_channels', [
            'subscriberid' => $subscriber->subscriberid,
            'channel' => 'booth',
            'domain' => 'booth.altfon.net',
        ]);

        SubscriberAuthModel::where('subscriberid', $subscriber->subscriberid)
            ->get()
            ->each(function (SubscriberAuthModel $auth) {
                $this->assertSame(SubscriberAuthModel::PASSWORD_LENGTH, strlen($auth->authpassword));
            });
    }

    public function test_mobile_can_create_and_update_sip_accounts_with_a_long_password(): void
    {
        $subscriber = $this->mobileAccount('authpassuser', '0245555000');

        $login = $this->postJson('/api/v1/login', [
            'username' => $subscriber->username,
            'password' => 'password123',
        ])->assertOk();

        $token = $login->json('data.access_token');

        $created = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth-users')
            ->assertCreated()
            ->json();

        $this->assertSame(SubscriberAuthModel::PASSWORD_LENGTH, strlen($created['authpassword']));

        $auth = SubscriberAuthModel::where('authusername', $created['authusername'])->firstOrFail();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/update-auth-account', [
                'id' => $auth->id,
                'authusername' => $auth->authusername,
                'authpassword' => 'too-short',
            ])
            ->assertStatus(422);
    }

    public function test_admin_rejects_short_sip_passwords(): void
    {
        Http::fake();
        $admin = $this->admin();
        $subscriber = $this->mobileAccount('shortpassuser', '0246666000');

        $this->actingAs($admin, 'admin-api')->postJson('/api/admin/auth-users', [
            'subscriberid' => $subscriber->subscriberid,
            'channel' => 'mobile',
            'authusername' => '900001',
            'authpassword' => 'too-short',
        ])->assertStatus(422);
    }

    public function test_ussd_accounts_include_both_channels(): void
    {
        Http::fake();
        $admin = $this->admin();
        $ghana = Country::where('name', 'Ghana')->firstOrFail();

        $this->actingAs($admin, 'admin-api')->postJson('/api/admin/subscribers', [
            'fullname' => 'USSD Both',
            'username' => 'ussdboth',
            'emailaddress' => 'ussdboth@example.com',
            'phonenumber' => '0244444000',
            'password' => 'password123',
            'country_id' => $ghana->id,
            'has_mobile_account' => true,
            'has_booth_account' => true,
        ])->assertCreated();

        $subscriber = SubscribersModel::where('username', 'ussdboth')->firstOrFail();
        $summaries = app(UssdSubscriberService::class)->accountSummaries($subscriber);

        $this->assertSame(2, $summaries->count());
        $this->assertEqualsCanonicalizing(['mobile', 'booth'], $summaries->pluck('channel')->all());

        $booth = $summaries->firstWhere('channel', 'booth');
        $this->assertSame('booth.altfon.net', $booth['domain']);
    }

    public function test_admin_country_crud_creates_relay_and_blocks_referenced_delete(): void
    {
        Http::fake();
        $admin = $this->admin();

        $create = $this->actingAs($admin, 'admin-api')->postJson('/api/admin/countries', [
            'name' => 'Ivory Coast',
            'iso_code' => 'CI',
            'dial_code' => '225',
            'domain' => 'ivorycoast.altfon.net',
        ])->assertCreated()->json();

        $this->assertDatabaseHas('voice_relay', ['domain' => 'ivorycoast.altfon.net']);

        $countryId = $create['data']['id'];

        $this->actingAs($admin, 'admin-api')->putJson('/api/admin/countries/' . $countryId, [
            'is_active' => false,
        ])->assertOk();

        $this->actingAs($admin, 'admin-api')->deleteJson('/api/admin/countries/' . $countryId)
            ->assertOk();
    }

    public function test_reserved_country_cannot_be_deleted_when_used(): void
    {
        Http::fake();
        $admin = $this->admin();
        $subscriber = $this->mobileAccount();
        $profile = $subscriber->channelProfile('mobile');

        $this->actingAs($admin, 'admin-api')
            ->deleteJson('/api/admin/countries/' . $profile->country_id)
            ->assertStatus(409);
    }
}
