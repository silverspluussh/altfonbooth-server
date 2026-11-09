<?php

namespace Tests\Feature;

use App\Models\SubscribersModel;
use App\Models\SubscribersTempModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhoneNormalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        config([
            'services.ussd.internal_token' => 'test-internal-token',
            'services.ussd.internal_ip_allowlist' => [],
        ]);
    }

    private function signup(string $phonenumber, string $username = 'normtest'): array
    {
        $response = $this->postJson('/api/signup', [
            'username' => $username,
            'password' => 'Password123',
            'confirm_password' => 'Password123',
            'phonenumber' => $phonenumber,
            'fullname' => 'Normalize Test',
            'emailaddress' => $username . '@example.com',
            'country' => 'Ghana',
        ]);

        $response->assertStatus(201);

        return $response->json();
    }

    public function test_signup_stores_ghana_numbers_in_local_format(): void
    {
        $this->signup('+233249335549');

        $this->assertDatabaseHas('subscribers_temp', ['phonenumber' => '0249335549']);
        $this->assertDatabaseMissing('subscribers_temp', ['phonenumber' => '+233249335549']);
    }

    public function test_signup_stores_digits_for_non_ghana_numbers(): void
    {
        $this->signup('+234 801 234 5678', 'naijauser');

        $this->assertDatabaseHas('subscribers_temp', ['phonenumber' => '2348012345678']);
    }

    public function test_signup_rejects_blank_phone_numbers(): void
    {
        $this->postJson('/api/signup', [
            'username' => 'blankphone',
            'password' => 'Password123',
            'confirm_password' => 'Password123',
            'phonenumber' => '   ',
            'fullname' => 'Blank Phone',
            'emailaddress' => 'blankphone@example.com',
            'country' => 'Ghana',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phonenumber']);
    }

    public function test_verify_creates_the_subscriber_in_local_format(): void
    {
        $payload = $this->signup('+233249335549');

        $this->postJson('/api/verify', [
            'phonenumber' => '+233249335549',
            'otp' => $payload['debug_otp'],
        ])->assertOk()->assertJsonPath('status', true);

        $this->assertDatabaseHas('subscribers', ['phonenumber' => '0249335549']);
        $this->assertDatabaseMissing('subscribers', ['phonenumber' => '+233249335549']);
    }

    public function test_verify_matches_pending_rows_saved_before_normalization(): void
    {
        SubscribersTempModel::create([
            'subscriberid' => 'TEMP_LEGACY',
            'fullname' => 'Legacy User',
            'username' => 'legacyuser',
            'password' => bcrypt('Password123'),
            'phonenumber' => '+233249335549',
            'emailaddress' => 'legacy@example.com',
            'country' => 'Ghana',
            'otp' => '123456',
            'otp_expiration' => now()->addMinutes(10),
            'status' => 'pending',
        ]);

        $this->postJson('/api/verify', [
            'phonenumber' => '+233249335549',
            'otp' => '123456',
        ])->assertOk();

        $this->assertDatabaseHas('subscribers', ['phonenumber' => '0249335549']);
    }

    public function test_ussd_lookup_finds_a_subscriber_who_signed_up_with_a_country_code(): void
    {
        $payload = $this->signup('+233249335549', 'ussdlookup');

        $this->postJson('/api/verify', [
            'phonenumber' => '+233249335549',
            'otp' => $payload['debug_otp'],
        ])->assertOk();

        $subscriber = SubscribersModel::where('phonenumber', '0249335549')->firstOrFail();

        $this->withHeader('X-Internal-Token', 'test-internal-token')
            ->postJson('/api/internal/ussd/lookup', ['msisdn' => '233249335549'])
            ->assertOk()
            ->assertJsonPath('data.registered', true)
            ->assertJsonPath('data.subscriber.subscriberid', $subscriber->subscriberid)
            ->assertJsonCount(1, 'data.accounts');
    }

    public function test_password_reset_finds_a_subscriber_by_any_phone_format(): void
    {
        $payload = $this->signup('+233249335549', 'resetuser');

        $this->postJson('/api/verify', [
            'phonenumber' => '+233249335549',
            'otp' => $payload['debug_otp'],
        ])->assertOk();

        $this->postJson('/api/password/request', [
            'phonenumber' => '233249335549',
        ])->assertOk()->assertJsonPath('status', true);
    }
}
