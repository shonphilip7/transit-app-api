<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receives_token(): void
    {
        $payload = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
        ];
        $response = $this->postJson('/api/register', $payload);
        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'token',
            ]);
        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
        ]);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => Hash::make('Password123!'),
        ]);
        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'Password123!',
        ]);
        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'token',
            ]);
        // Verify a personal access token was actually stored in the database
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => Hash::make('Password123!'),
        ]);
        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'WrongPassword',
        ]);
        $response->assertOk()
            ->assertJsonStructure([
                'message',
            ]);
    }

    public function test_authenticated_user_can_logout_and_revoke_token(): void
    {
        // Automatically generates a random valid email and password
        $user = User::factory()->create();
        // Create a real token
        $token = $user->createToken('test-device')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout');
        $response->assertOk();
        // Verify the token was deleted from the database
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
