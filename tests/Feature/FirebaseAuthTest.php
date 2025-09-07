<?php

use App\Domain\User\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mock Firebase public keys for testing
    $publicKeys = [
        'test-kid' => file_get_contents(base_path('tests/fixtures/firebase-public-key.pem')),
    ];
    
    Cache::put('firebase_public_keys', $publicKeys, 3600);
});

test('can exchange valid firebase token for sanctum token', function () {
    $firebaseToken = createValidFirebaseToken([
        'sub' => 'firebase-uid-123',
        'email' => 'test@example.com',
        'name' => 'Test User',
        'email_verified' => true,
        'picture' => 'https://example.com/avatar.jpg',
    ]);

    $response = $this->postJson('/api/auth/exchange', [], [
        'Authorization' => "Bearer {$firebaseToken}",
    ]);
    
    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Authentication successful',
            'user' => [
                'email' => 'test@example.com',
                'name' => 'Test User',
            ],
        ])
        ->assertJsonStructure(['token', 'user', 'message']);
        
    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'firebase_uid' => 'firebase-uid-123',
    ]);
});

test('creates new user on first firebase auth', function () {
    $this->assertDatabaseCount('users', 0);

    $firebaseToken = createValidFirebaseToken([
        'sub' => 'new-firebase-uid',
        'email' => 'newuser@example.com',
        'name' => 'New User',
        'email_verified' => false,
    ]);

    $response = $this->postJson('/api/auth/exchange', [], [
        'Authorization' => "Bearer {$firebaseToken}",
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseCount('users', 1);
    
    $user = User::first();
    expect($user->firebase_uid)->toBe('new-firebase-uid');
    expect($user->email)->toBe('newuser@example.com');
    expect($user->name)->toBe('New User');
    expect($user->email_verified_at)->toBeNull();
});

test('updates existing user with firebase data', function () {
    // Create existing user
    $user = User::factory()->create([
        'email' => 'existing@example.com',
        'name' => 'Old Name',
        'firebase_uid' => null,
    ]);

    $firebaseToken = createValidFirebaseToken([
        'sub' => 'firebase-uid-456',
        'email' => 'existing@example.com',
        'name' => 'Updated Name',
        'email_verified' => true,
        'picture' => 'https://example.com/new-avatar.jpg',
    ]);

    $response = $this->postJson('/api/auth/exchange', [], [
        'Authorization' => "Bearer {$firebaseToken}",
    ]);

    $response->assertStatus(200);
    
    $user->refresh();
    expect($user->firebase_uid)->toBe('firebase-uid-456');
    expect($user->name)->toBe('Updated Name');
    expect($user->avatar_url)->toBe('https://example.com/new-avatar.jpg');
    expect($user->email_verified_at)->not->toBeNull();
});

test('finds user by firebase uid on subsequent logins', function () {
    // Create user with firebase uid
    $user = User::factory()->create([
        'firebase_uid' => 'existing-firebase-uid',
        'email' => 'user@example.com',
    ]);

    $firebaseToken = createValidFirebaseToken([
        'sub' => 'existing-firebase-uid',
        'email' => 'different@example.com', // Different email
        'name' => 'Updated Name',
    ]);

    $response = $this->postJson('/api/auth/exchange', [], [
        'Authorization' => "Bearer {$firebaseToken}",
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'user' => ['id' => $user->id],
        ]);

    // Should not create new user
    $this->assertDatabaseCount('users', 1);
});

test('rejects request without firebase token', function () {
    $response = $this->postJson('/api/auth/exchange');

    $response->assertStatus(401)
        ->assertJson(['error' => 'No token provided']);
});

test('rejects invalid firebase token', function () {
    $response = $this->postJson('/api/auth/exchange', [], [
        'Authorization' => 'Bearer invalid-token',
    ]);

    $response->assertStatus(401)
        ->assertJson(['error' => 'Invalid token']);
});

test('rejects malformed firebase token', function () {
    $response = $this->postJson('/api/auth/exchange', [], [
        'Authorization' => 'Bearer not.a.valid.jwt.format',
    ]);

    $response->assertStatus(401)
        ->assertJson(['error' => 'Invalid token']);
});

test('rejects firebase token with wrong kid', function () {
    $firebaseToken = createFirebaseTokenWithKid('wrong-kid', [
        'sub' => 'firebase-uid-123',
        'email' => 'test@example.com',
    ]);

    $response = $this->postJson('/api/auth/exchange', [], [
        'Authorization' => "Bearer {$firebaseToken}",
    ]);

    $response->assertStatus(401)
        ->assertJson(['error' => 'Invalid token']);
});

test('can use sanctum token from exchange for authenticated requests', function () {
    $firebaseToken = createValidFirebaseToken([
        'sub' => 'firebase-uid-789',
        'email' => 'api-user@example.com',
        'name' => 'API User',
    ]);

    $response = $this->postJson('/api/auth/exchange', [], [
        'Authorization' => "Bearer {$firebaseToken}",
    ]);

    $response->assertStatus(200);
    $sanctumToken = $response->json('token');

    // Use Sanctum token to access protected endpoint
    $response = $this->getJson('/api/auth/me', [
        'Authorization' => "Bearer {$sanctumToken}",
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'user' => [
                'email' => 'api-user@example.com',
                'name' => 'API User',
            ],
        ]);
});

// Helper functions
function createValidFirebaseToken(array $payload = []): string
{
    return createFirebaseTokenWithKid('test-kid', $payload);
}

function createFirebaseTokenWithKid(string $kid, array $payload = []): string
{
    $privateKey = file_get_contents(base_path('tests/fixtures/firebase-private-key.pem'));
    
    $defaultPayload = [
        'iss' => 'https://securetoken.google.com/test-project',
        'aud' => 'test-project',
        'auth_time' => time(),
        'user_id' => 'test-user-id',
        'sub' => 'test-user-id',
        'iat' => time(),
        'exp' => time() + 3600,
        'email' => 'test@example.com',
        'email_verified' => false,
        'firebase' => [
            'identities' => [
                'email' => ['test@example.com'],
            ],
            'sign_in_provider' => 'password',
        ],
    ];

    $payload = array_merge($defaultPayload, $payload);
    
    return JWT::encode($payload, $privateKey, 'RS256', $kid);
}
