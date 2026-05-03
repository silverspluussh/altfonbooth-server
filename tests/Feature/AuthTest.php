<?php

namespace Tests\Feature;

use App\Models\SubscribersModel;
use App\Models\SubscriberTempModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful signup.
     */
    public function test_subscriber_can_signup(): void
    {
        Http::fake();
        Mail::fake();

        $payload = [
            'fullname' => 'Test User',
            'username' => 'testuser',
            'password' => 'password123',
            'phonenumber' => '0240000000',
            'emailaddress' => 'test@example.com',
            'country' => 'Ghana',
        ];

        $response = $this->postJson('/api/signup', $payload);

        $response->assertStatus(201)
                 ->assertJson(['status' => true]);

        $this->assertDatabaseHas('subscribers_temp', [
            'username' => 'testuser',
            'emailaddress' => 'test@example.com'
        ]);
    }

    /**
     * Test OTP verification and migration.
     */
    public function test_subscriber_can_verify_otp(): void
    {
        $temp = SubscriberTempModel::create([
            'subscriberid' => 'TEMP_12345',
            'fullname' => 'Verify User',
            'username' => 'verifyuser',
            'password' => bcrypt('password123'),
            'phonenumber' => '0550000000',
            'emailaddress' => 'verify@example.com',
            'country' => 'Ghana',
            'otp' => '123456',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/verify', [
            'subscriberid' => $temp->subscriberid,
            'otp' => '123456'
        ]);

        $response->assertStatus(200)
                 ->assertJson(['status' => true]);

        $this->assertDatabaseHas('subscribers', [
            'username' => 'verifyuser'
        ]);

        $this->assertDatabaseMissing('subscribers_temp', [
            'id' => $temp->id
        ]);
    }

    /**
     * Test login.
     */
    public function test_subscriber_can_login(): void
    {
        $sub = SubscribersModel::create([
            'subscriberid' => 'SUB_123',
            'fullname' => 'Login User',
            'username' => 'loginuser',
            'password' => bcrypt('password123'),
            'phonenumber' => '0200000000',
            'emailaddress' => 'login@example.com',
            'country' => 'Ghana',
        ]);

        $response = $this->postJson('/api/login', [
            'username' => 'loginuser',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['status', 'token', 'user']);
    }
}
