<?php

namespace Tests\Feature\Api;

/**
 * API authentication: login, logout, /me endpoint.
 */
class AuthApiTest extends ApiTestCase
{
    // ── Login ─────────────────────────────────────────────────────────────────

    public function test_login_returns_token_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'       => 'admin@testschool.edu.gh',
            'password'    => 'Admin@12345',
            'school_slug' => 'test-school',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email'], 'school' => ['id', 'slug', 'name']]);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email'       => 'admin@testschool.edu.gh',
            'password'    => 'wrong',
            'school_slug' => 'test-school',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_login_with_invalid_slug_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email'       => 'admin@testschool.edu.gh',
            'password'    => 'Admin@12345',
            'school_slug' => 'no-such-school',
        ])->assertStatus(422)->assertJsonValidationErrors('school_slug');
    }

    public function test_login_requires_all_fields(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password', 'school_slug']);
    }

    // ── Me ────────────────────────────────────────────────────────────────────

    public function test_me_returns_authenticated_user(): void
    {
        $response = $this->apiAs('getJson', '/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.email', 'admin@testschool.edu.gh')
            ->assertJsonPath('school.slug', 'test-school');
    }

    public function test_me_requires_auth(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function test_logout_revokes_token(): void
    {
        $token = $this->token();

        $this->withToken($token)->deleteJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        // Verify the token was deleted from the database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id'   => $this->user->id,
            'tokenable_type' => $this->user::class,
        ]);
    }
}
