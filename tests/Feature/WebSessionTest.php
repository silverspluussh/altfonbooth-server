<?php

namespace Tests\Feature;

use App\Models\AdminModel;
use App\Models\SubscribersModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.blacklist_enabled' => true,
            'jwt.blacklist_grace_period' => 0,
        ]);
    }

    private function forgetGuards(): void
    {
        // The JWT guard and JWT singleton cache the authenticated user/token
        // for the test process; reset both so each request re-parses the token.
        app('auth')->forgetGuards();
        app('tymon.jwt')->unsetToken();
    }

    private function subscriber(): SubscribersModel
    {
        return SubscribersModel::create([
            'subscriberid' => 'SUB_SESSION1',
            'fullname' => 'Session Test',
            'username' => 'sessiontest',
            'password' => bcrypt('Password123'),
            'phonenumber' => '0249335549',
            'emailaddress' => 'session@example.com',
            'country' => 'Ghana',
            'authusername' => '7022875',
            'switch_status' => 'active',
            'billing_acc_status' => 'active',
        ]);
    }

    private function admin(): AdminModel
    {
        return AdminModel::create([
            'name' => 'Session Admin',
            'username' => 'sessionadmin',
            'email' => 'sessionadmin@example.com',
            'password' => 'Password123',
            'role' => 'manager',
        ]);
    }

    public function test_login_returns_expiry_metadata(): void
    {
        $this->subscriber();

        $response = $this->postJson('/api/login', [
            'username' => 'sessiontest',
            'password' => 'Password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['token', 'expires_in', 'expires_at', 'user']);

        $this->assertIsInt($response->json('expires_in'));
        $this->assertGreaterThan(0, $response->json('expires_in'));
    }

    public function test_refresh_rotates_the_token_and_blacklists_the_old_one(): void
    {
        $this->subscriber();

        $login = $this->postJson('/api/login', [
            'username' => 'sessiontest',
            'password' => 'Password123',
        ])->assertOk();

        $oldToken = $login->json('token');

        $this->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->getJson('/api/user')
            ->assertOk();

        $this->forgetGuards();
        $refresh = $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->postJson('/api/refresh')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['token', 'expires_in', 'expires_at']);

        $newToken = $refresh->json('token');
        $this->assertNotSame($oldToken, $newToken);


        $this->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->getJson('/api/user')
            ->assertStatus(401);

        $this->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $newToken)
            ->getJson('/api/user')
            ->assertOk();
    }

    public function test_logout_blacklists_the_current_token(): void
    {
        $this->subscriber();

        $token = $this->postJson('/api/login', [
            'username' => 'sessiontest',
            'password' => 'Password123',
        ])->assertOk()->json('token');

        $this->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/user')
            ->assertStatus(401);
    }

    public function test_admin_refresh_and_logout_use_the_admin_guard(): void
    {
        $this->admin();

        $login = $this->postJson('/api/admin/login', [
            'username' => 'sessionadmin',
            'password' => 'Password123',
        ])->assertOk()->assertJsonStructure(['token', 'expires_in', 'expires_at', 'admin']);

        $oldToken = $login->json('token');

        $this->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->getJson('/api/admin/dashboard')
            ->assertOk();

        $this->forgetGuards();
        $refresh = $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->postJson('/api/admin/refresh')
            ->assertOk()
            ->assertJsonStructure(['token', 'expires_in', 'expires_at']);

        $newToken = $refresh->json('token');

        $this->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->getJson('/api/admin/dashboard')
            ->assertStatus(401);

        $this->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $newToken)
            ->getJson('/api/admin/dashboard')
            ->assertOk();

        $this->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $newToken)
            ->postJson('/api/admin/logout')
            ->assertOk();

        $this->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $newToken)
            ->getJson('/api/admin/dashboard')
            ->assertStatus(401);
    }

    public function test_refresh_accepts_an_expired_token_within_the_refresh_window(): void
    {
        $this->subscriber();

        $oldToken = $this->postJson('/api/login', [
            'username' => 'sessiontest',
            'password' => 'Password123',
        ])->assertOk()->json('token');

        // Access token lives 720 minutes; move past it but stay inside the
        // 14-day refresh window.
        $this->travel(721)->minutes();

        $this->forgetGuards();
        $refresh = $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->postJson('/api/refresh')
            ->assertOk()
            ->assertJsonStructure(['token', 'expires_in', 'expires_at']);

        $newToken = $refresh->json('token');
        $this->assertNotSame($oldToken, $newToken);

        $this->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $newToken)
            ->getJson('/api/user')
            ->assertOk();
    }
}
