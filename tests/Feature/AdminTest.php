<?php

namespace Tests\Feature;

use App\Models\AdminModel;
use App\Models\SubscribersModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin login.
     */
    public function test_admin_can_login(): void
    {
        AdminModel::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'manager',
        ]);

        $response = $this->postJson('/api/admin/login', [
            'username' => 'admin',
            'password' => 'password'
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['status', 'token', 'admin']);
    }

    /**
     * Test manager can list subscribers.
     */
    public function test_manager_can_list_subscribers(): void
    {
        $admin = AdminModel::create([
            'name' => 'Manager',
            'username' => 'manager',
            'email' => 'manager@example.com',
            'password' => 'password',
            'role' => 'manager',
        ]);

        SubscribersModel::create([
            'subscriberid' => 'SUB_1',
            'fullname' => 'Sub 1',
            'username' => 'sub1',
            'password' => 'pass',
            'phonenumber' => '1',
            'emailaddress' => 'sub1@ex.com',
            'country' => 'GHA',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->getJson('/api/admin/subscribers');

        $response->assertStatus(200)
                 ->assertJsonCount(1);
    }

    /**
     * Test only super_admin can manage other admins.
     */
    public function test_manager_cannot_create_admins(): void
    {
        $admin = AdminModel::create([
            'name' => 'Manager',
            'username' => 'manager',
            'email' => 'manager@ex.com',
            'password' => 'password',
            'role' => 'manager',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson('/api/admin/admins', [
                             'name' => 'New Admin',
                             'username' => 'newadmin',
                             'email' => 'new@ex.com',
                             'password' => 'password',
                             'role' => 'manager'
                         ]);

        $response->assertStatus(403);
    }
}
