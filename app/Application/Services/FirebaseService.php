<?php

namespace App\Application\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Exception\Auth\InvalidToken;
use Kreait\Firebase\Exception\FirebaseException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    private Auth $auth;
    private bool $enabled;

    public function __construct()
    {
        $this->enabled = config('firebase.project_id') && config('firebase.project_id') !== 'your-firebase-project-id';
        
        if ($this->enabled) {
            try {
                $factory = (new Factory)->withServiceAccount(config('firebase.credentials'));
                $this->auth = $factory->createAuth();
            } catch (\Exception $e) {
                Log::warning('Firebase initialization failed: ' . $e->getMessage());
                $this->enabled = false;
            }
        }
    }

    /**
     * Verify Firebase ID token and return user data
     */
    public function verifyToken(string $idToken): array
    {
        if (!$this->enabled) {
            throw new \Exception('Firebase is not configured. Please set up Firebase credentials in .env file.');
        }

        try {
            $verifiedIdToken = $this->auth->verifyIdToken($idToken);
            $claims = $verifiedIdToken->claims();

            return [
                'uid' => $claims->get('sub'),
                'email' => $claims->get('email'),
                'name' => $claims->get('name') ?? $claims->get('email'),
                'email_verified' => $claims->get('email_verified', false),
                'provider' => $claims->get('firebase', [])['sign_in_provider'] ?? 'firebase',
                'picture' => $claims->get('picture'),
                'issued_at' => $verifiedIdToken->issuedAt(),
                'expires_at' => $verifiedIdToken->expiresAt(),
            ];
        } catch (InvalidToken $e) {
            throw new \Exception('Invalid Firebase token: ' . $e->getMessage());
        } catch (FirebaseException $e) {
            throw new \Exception('Firebase error: ' . $e->getMessage());
        }
    }

    /**
     * Find existing user or create new one from Firebase data
     */
    public function findOrCreateUser(array $firebaseUser): User
    {
        // First try to find by Firebase UID
        $user = User::where('firebase_uid', $firebaseUser['uid'])->first();
        
        if ($user) {
            // Update last Firebase login and analytics data
            $user->update([
                'last_firebase_login' => now(),
                'firebase_analytics_data' => $this->buildAnalyticsData($firebaseUser),
            ]);
            return $user;
        }

        // Try to find by email and link Firebase account
        $user = User::where('email', $firebaseUser['email'])->first();
        
        if ($user) {
            // Link existing account to Firebase
            $user->update([
                'firebase_uid' => $firebaseUser['uid'],
                'provider' => $firebaseUser['provider'],
                'last_firebase_login' => now(),
                'firebase_analytics_data' => $this->buildAnalyticsData($firebaseUser),
                'email_verified_at' => $firebaseUser['email_verified'] ? now() : $user->email_verified_at,
            ]);
            return $user;
        }

        // Create new user
        return User::create([
            'firebase_uid' => $firebaseUser['uid'],
            'name' => $firebaseUser['name'],
            'email' => $firebaseUser['email'],
            'username' => $this->generateUniqueUsername($firebaseUser['email']),
            'email_verified_at' => $firebaseUser['email_verified'] ? now() : null,
            'password' => Hash::make(Str::random(32)), // Random password for Firebase users
            'provider' => $firebaseUser['provider'],
            'last_firebase_login' => now(),
            'firebase_analytics_data' => $this->buildAnalyticsData($firebaseUser),
        ]);
    }

    /**
     * Build analytics data from Firebase user information
     */
    private function buildAnalyticsData(array $firebaseUser): array
    {
        return [
            'provider' => $firebaseUser['provider'],
            'login_method' => $firebaseUser['provider'],
            'first_login' => false, // Will be updated on first creation
            'login_count' => 1,
            'last_login_ip' => request()->ip(),
            'last_login_user_agent' => request()->userAgent(),
            'firebase_created_at' => $firebaseUser['issued_at']->format('Y-m-d H:i:s'),
            'has_picture' => !empty($firebaseUser['picture']),
            'email_verified' => $firebaseUser['email_verified'],
        ];
    }

    /**
     * Generate unique username from email
     */
    private function generateUniqueUsername(string $email): string
    {
        $baseUsername = Str::slug(Str::before($email, '@'));
        $username = $baseUsername;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Track Firebase Analytics event
     */
    public function trackEvent(User $user, string $eventName, array $parameters = []): void
    {
        if (!config('firebase.analytics.enabled')) {
            return;
        }

        try {
            // Merge user context with event parameters
            $analyticsData = array_merge([
                'user_id' => $user->id,
                'firebase_uid' => $user->firebase_uid,
                'provider' => $user->provider,
                'timestamp' => now()->toISOString(),
            ], $parameters);

            // Store in user's analytics data for aggregation
            $currentData = $user->firebase_analytics_data ?? [];
            $currentData['events'][] = [
                'name' => $eventName,
                'parameters' => $analyticsData,
                'timestamp' => now()->toISOString(),
            ];

            // Keep only last 100 events to prevent bloat
            if (count($currentData['events'] ?? []) > 100) {
                $currentData['events'] = array_slice($currentData['events'], -100);
            }

            $user->update(['firebase_analytics_data' => $currentData]);

            if (config('firebase.analytics.debug')) {
                Log::info('Firebase Analytics Event', [
                    'event' => $eventName,
                    'user_id' => $user->id,
                    'parameters' => $analyticsData,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Firebase Analytics tracking failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if Firebase is properly configured
     */
    public function isConfigured(): bool
    {
        return $this->enabled;
    }
}
