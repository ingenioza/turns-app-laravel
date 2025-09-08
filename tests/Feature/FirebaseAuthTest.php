<?php

namespace Tests\Feature;

use App\Models\User;
use App\Application\Services\FirebaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class FirebaseAuthTest extends TestCase
{
    use RefreshDatabase;

    private FirebaseService $firebaseService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->firebaseService = Mockery::mock(FirebaseService::class);
        $this->app->instance(FirebaseService::class, $this->firebaseService);
    }

    public function test_firebase_exchange_returns_not_configured_when_firebase_not_setup(): void
    {
        $this->firebaseService
            ->shouldReceive('isConfigured')
            ->once()
            ->andReturn(false);

        $response = $this->postJson('/api/auth/firebase/exchange', [
            'idToken' => 'fake-firebase-token',
        ]);

        $response->assertStatus(501)
            ->assertJson([
                'message' => 'Firebase authentication is not configured',
                'note' => 'Please configure Firebase credentials in .env file',
                'fallback' => 'Use /auth/register and /auth/login endpoints for development',
            ]);
    }

    public function test_firebase_exchange_creates_new_user_successfully(): void
    {
        $firebaseUserData = [
            'uid' => 'firebase-uid-123',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'email_verified' => true,
            'provider' => 'google.com',
            'picture' => 'https://example.com/avatar.jpg',
        ];

        $this->firebaseService
            ->shouldReceive('isConfigured')
            ->once()
            ->andReturn(true);

        $this->firebaseService
            ->shouldReceive('verifyToken')
            ->with('valid-firebase-token')
            ->once()
            ->andReturn($firebaseUserData);

        $user = User::factory()->make([
            'firebase_uid' => $firebaseUserData['uid'],
            'email' => $firebaseUserData['email'],
            'name' => $firebaseUserData['name'],
            'provider' => $firebaseUserData['provider'],
        ]);

        $this->firebaseService
            ->shouldReceive('findOrCreateUser')
            ->with($firebaseUserData)
            ->once()
            ->andReturn($user);

        $this->firebaseService
            ->shouldReceive('trackEvent')
            ->once();

        $response = $this->postJson('/api/auth/firebase/exchange', [
            'idToken' => 'valid-firebase-token',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
                'token',
                'firebase_data' => [
                    'provider',
                    'email_verified',
                ],
            ]);
    }

    public function test_firebase_exchange_handles_invalid_token(): void
    {
        $this->firebaseService
            ->shouldReceive('isConfigured')
            ->once()
            ->andReturn(true);

        $this->firebaseService
            ->shouldReceive('verifyToken')
            ->with('invalid-firebase-token')
            ->once()
            ->andThrow(new \Exception('Invalid Firebase token'));

        $response = $this->postJson('/api/auth/firebase/exchange', [
            'idToken' => 'invalid-firebase-token',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Firebase authentication failed',
                'error' => 'Invalid Firebase token',
                'fallback' => 'Use /auth/register and /auth/login endpoints for development',
            ]);
    }

    public function test_firebase_exchange_links_existing_user_account(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'firebase_uid' => null,
        ]);

        $firebaseUserData = [
            'uid' => 'firebase-uid-456',
            'email' => 'existing@example.com',
            'name' => 'Existing User',
            'email_verified' => true,
            'provider' => 'password',
        ];

        $this->firebaseService
            ->shouldReceive('isConfigured')
            ->once()
            ->andReturn(true);

        $this->firebaseService
            ->shouldReceive('verifyToken')
            ->once()
            ->andReturn($firebaseUserData);

        $this->firebaseService
            ->shouldReceive('findOrCreateUser')
            ->with($firebaseUserData)
            ->once()
            ->andReturn($existingUser);

        $this->firebaseService
            ->shouldReceive('trackEvent')
            ->once();

        $response = $this->postJson('/api/auth/firebase/exchange', [
            'idToken' => 'valid-firebase-token',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'existing@example.com');
    }

    public function test_firebase_exchange_requires_id_token(): void
    {
        $response = $this->postJson('/api/auth/firebase/exchange', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['idToken']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
