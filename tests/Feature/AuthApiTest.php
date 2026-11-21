<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pelapor_can_register(): void
    {
        $payload = [
            'full_name' => 'Budi Santoso',
            'email' => 'budi.santoso@uii.ac.id',
            'password' => 'Password123!',
            'reporter_type' => 'MAHASISWA',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'msg',
                'data' => [
                    'user' => ['uuid', 'full_name', 'email', 'role', 'reporter_type'],
                    'token',
                ],
            ]);

        $userData = $response->json('data.user');
        $this->assertArrayNotHasKey('id', $userData, 'ID internal tidak boleh bocor ke client!');
        $this->assertEquals('PELAPOR', $userData['role']);
        $this->assertEquals('MAHASISWA', $userData['reporter_type']);

        $this->assertDatabaseHas('users', [
            'email' => 'budi.santoso@uii.ac.id',
            'role' => 'PELAPOR',
        ]);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::create([
            'full_name' => 'Siti Aminah',
            'email' => 'siti@uii.ac.id',
            'password' => Hash::make('secret123'),
            'role' => 'PELAPOR',
            'reporter_type' => 'DOSEN',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'siti@uii.ac.id',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'msg',
                'data' => [
                    'user' => ['uuid', 'full_name', 'email'],
                    'token',
                ],
            ]);

        $this->assertArrayNotHasKey('id', $response->json('data.user'));
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::create([
            'full_name' => 'Test User',
            'email' => 'test@uii.ac.id',
            'password' => Hash::make('correct_pass'),
            'role' => 'PELAPOR',
            'reporter_type' => 'MAHASISWA',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@uii.ac.id',
            'password' => 'wrong_pass',
        ]);

        $response->assertStatus(401);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::create([
            'full_name' => 'Disabled User',
            'email' => 'disabled@uii.ac.id',
            'password' => Hash::make('pass123'),
            'role' => 'PELAPOR',
            'reporter_type' => 'STAF',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'disabled@uii.ac.id',
            'password' => 'pass123',
        ]);

        $response->assertStatus(403);
    }

    public function test_me_endpoint_returns_current_authenticated_user(): void
    {
        $user = User::create([
            'full_name' => 'Active User',
            'email' => 'active@uii.ac.id',
            'password' => Hash::make('pass123'),
            'role' => 'PELAPOR',
            'reporter_type' => 'MAHASISWA',
            'is_active' => true,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'user' => [
                        'uuid' => $user->uuid,
                        'email' => 'active@uii.ac.id',
                    ],
                ],
            ]);

        $this->assertArrayNotHasKey('id', $response->json('data.user'));
    }

    public function test_change_password_works_properly(): void
    {
        $user = User::create([
            'full_name' => 'Change Pass User',
            'email' => 'changepass@uii.ac.id',
            'password' => Hash::make('old_pass_123'),
            'role' => 'TEKNISI',
            'must_change_password' => true,
            'is_active' => true,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/change-password', [
                'current_password' => 'old_pass_123',
                'new_password' => 'new_secure_pass_456',
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('new_secure_pass_456', $user->password));
        $this->assertFalse($user->must_change_password);
    }

    public function test_logout_invalidates_token(): void
    {
        $user = User::create([
            'full_name' => 'Logout User',
            'email' => 'logout@uii.ac.id',
            'password' => Hash::make('secret'),
            'role' => 'PELAPOR',
            'is_active' => true,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200);

        // Assert token deleted from database
        $this->assertDatabaseEmpty('personal_access_tokens');

        // Next request with same token should be unauthenticated
        $this->app['auth']->forgetGuards();
        $check = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');

        $check->assertStatus(401);
    }
}
