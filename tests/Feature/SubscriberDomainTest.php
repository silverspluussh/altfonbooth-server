<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminController;
use App\Models\SubscribersModel;
use App\Models\VoiceRelay;
use App\Support\SubscriberDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriberDomainTest extends TestCase
{
    use RefreshDatabase;

    private function seedRelay(): VoiceRelay
    {
        return VoiceRelay::create([
            'domain' => 'ghana.altfon.net',
            'port' => '5060',
            'protocol' => 'udp',
            'outboundproxy' => '203.161.38.171',
        ]);
    }

    private function signup(string $username, string $phone, ?string $country): array
    {
        $payload = [
            'username' => $username,
            'password' => 'Password123',
            'confirm_password' => 'Password123',
            'phonenumber' => $phone,
            'fullname' => 'Domain Test',
            'emailaddress' => $username . '@example.com',
        ];

        if ($country !== null) {
            $payload['country'] = $country;
        }

        return $this->postJson('/api/signup', $payload)->assertStatus(201)->json();
    }

    public function test_country_is_normalized_to_a_lowercase_domain(): void
    {
        $this->assertSame('ghana.altfon.net', SubscriberDomain::fromCountry('Ghana'));
        $this->assertSame('ghana.altfon.net', SubscriberDomain::fromCountry('  GHANA  '));
        $this->assertSame('unitedstates.altfon.net', SubscriberDomain::fromCountry('United States'));
        $this->assertSame('unitedkingdom.altfon.net', SubscriberDomain::fromCountry('United Kingdom'));
        $this->assertSame('nigeria.altfon.net', SubscriberDomain::fromCountry('Nigeria'));
        $this->assertSame('ghana.altfon.net', SubscriberDomain::fromCountry(null));
        $this->assertSame('ghana.altfon.net', SubscriberDomain::fromCountry(''));
    }

    public function test_ensure_relay_copies_the_shared_attributes(): void
    {
        $this->seedRelay();

        $relay = SubscriberDomain::ensureRelay('unitedstates.altfon.net');

        $this->assertSame('unitedstates.altfon.net', $relay->domain);
        $this->assertSame('5060', $relay->port);
        $this->assertSame('udp', $relay->protocol);
        $this->assertSame('203.161.38.171', $relay->outboundproxy);

        // Idempotent.
        $again = SubscriberDomain::ensureRelay('UNITEDSTATES.ALTFON.NET');
        $this->assertSame($relay->recid, $again->recid);
        $this->assertSame(2, VoiceRelay::count());
    }

    public function test_verification_saves_the_domain_and_creates_the_relay_row(): void
    {
        $this->seedRelay();
        Http::fake();

        $signup = $this->signup('domainuser', '0249335601', 'United States');

        $this->postJson('/api/verify', [
            'phonenumber' => '0249335601',
            'otp' => $signup['debug_otp'],
        ])->assertOk()
            ->assertJsonPath('data.domain', 'unitedstates.altfon.net');

        $this->assertDatabaseHas('subscribers', [
            'username' => 'domainuser',
            'domain' => 'unitedstates.altfon.net',
        ]);

        $this->assertDatabaseHas('voice_relay', [
            'domain' => 'unitedstates.altfon.net',
            'port' => '5060',
            'protocol' => 'udp',
            'outboundproxy' => '203.161.38.171',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'passjson.php')
                && $request['domain'] === 'unitedstates.altfon.net';
        });
    }

    public function test_missing_country_defaults_to_ghana(): void
    {
        $this->seedRelay();
        Http::fake();

        $signup = $this->signup('domaindefault', '0249335602', null);

        $this->postJson('/api/verify', [
            'phonenumber' => '0249335602',
            'otp' => $signup['debug_otp'],
        ])->assertOk()
            ->assertJsonPath('data.domain', 'ghana.altfon.net');

        $this->assertDatabaseHas('subscribers', [
            'username' => 'domaindefault',
            'domain' => 'ghana.altfon.net',
        ]);
    }

    public function test_admin_create_and_update_manage_the_domain(): void
    {
        $this->seedRelay();

        $controller = app(AdminController::class);

        $create = Request::create('/api/admin/subscribers', 'POST', [
            'fullname' => 'Admin Domain',
            'username' => 'admindomain',
            'emailaddress' => 'admindomain@example.com',
            'phonenumber' => '0249335603',
            'country' => 'United Kingdom',
            'password' => 'Password123',
        ]);

        $created = $controller->storeSubscriber($create)->getData(true);
        $subscriberid = $created['data']['subscriberid'];

        $this->assertSame('unitedkingdom.altfon.net', $created['data']['domain']);
        $this->assertDatabaseHas('voice_relay', ['domain' => 'unitedkingdom.altfon.net']);

        // The admin form pre-fills the current domain; changing only the
        // country must still recompute the derived domain.
        $update = Request::create('/api/admin/subscribers/' . $subscriberid, 'PUT', [
            'country' => 'Nigeria',
            'domain' => 'unitedkingdom.altfon.net',
        ]);

        $updated = $controller->updateSubscriber($update, $subscriberid)->getData(true);

        $this->assertSame('nigeria.altfon.net', $updated['data']['domain']);
        $this->assertDatabaseHas('subscribers', [
            'subscriberid' => $subscriberid,
            'domain' => 'nigeria.altfon.net',
        ]);

        $override = Request::create('/api/admin/subscribers/' . $subscriberid, 'PUT', [
            'domain' => 'custom.altfon.net',
        ]);

        $overridden = $controller->updateSubscriber($override, $subscriberid)->getData(true);

        $this->assertSame('custom.altfon.net', $overridden['data']['domain']);
        $this->assertDatabaseHas('voice_relay', ['domain' => 'custom.altfon.net']);
    }

    public function test_subscriber_model_exposes_domain(): void
    {
        $this->seedRelay();

        $subscriber = SubscribersModel::create([
            'subscriberid' => 'SUB_DOMAIN1',
            'fullname' => 'Domain Model',
            'username' => 'domainmodel',
            'password' => bcrypt('Password123'),
            'phonenumber' => '0249335604',
            'emailaddress' => 'domainmodel@example.com',
            'country' => 'Ghana',
            'domain' => 'ghana.altfon.net',
            'authusername' => '7022875',
            'switch_status' => 'active',
            'billing_acc_status' => 'active',
        ]);

        $this->assertSame('ghana.altfon.net', $subscriber->fresh()->domain);
    }
}
