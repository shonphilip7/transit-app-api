<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private const USER_NAME = 'Jane Doe';

    private const USER_EMAIL = 'jane@example.com';

    private const USER_PASSWORD = 'password123';

    public function test_user_can_register_and_receives_token(): void
    {
        $payload = [
            'name' => self::USER_NAME,
            'email' => self::USER_EMAIL,
            'password' => self::USER_PASSWORD,
        ];
        $response = $this->postJson('/api/register', $payload);
        $response->assertCreated()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'token',
                'token_type',
            ])
            ->assertJsonPath('user.email', self::USER_EMAIL);
        $this->assertDatabaseHas('users', [
            'email' => self::USER_EMAIL,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => self::USER_EMAIL]);
        $payload = [
            'name' => self::USER_NAME,
            'email' => self::USER_EMAIL,
            'password' => self::USER_PASSWORD,
        ];
        $response = $this->postJson('/api/register', $payload);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => self::USER_EMAIL,
            'password' => self::USER_PASSWORD,
        ]);
        $response = $this->postJson('/api/login', [
            'email' => self::USER_EMAIL,
            'password' => self::USER_PASSWORD,
        ]);
        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'token',
                'token_type',
            ]);
        // Check the personal_access_tokens table has an entry
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => self::USER_EMAIL,
            'password' => self::USER_PASSWORD,
        ]);
        $response = $this->postJson('/api/login', [
            'email' => self::USER_EMAIL,
            'password' => 'WrongPassword',
        ]);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_logout_and_revoke_token(): void
    {
        // Automatically generates a random valid email and password
        $user = User::factory()->create();
        // Create a real token
        $token = $user->createToken('test-device')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout');
        $response->assertOk()
            ->assertJson(['message' => 'Logged out successfully.']);
        // Verify the token was deleted from the database table
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
