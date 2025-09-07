<?php

namespace App\Http\Controllers\Api;

use App\Application\Services\UserService;
use App\Domain\User\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Exchange Firebase token for Sanctum token
     */
    public function exchange(Request $request): JsonResponse
    {
        try {
            $firebaseUser = $request->attributes->get('firebase_user');

            if (! $firebaseUser) {
                return response()->json(['error' => 'No Firebase user data'], 400);
            }

            // Map Firebase claims to user data
            $userData = $this->mapFirebaseToUserData($firebaseUser);

            // Create or update user
            $user = $this->userService->createOrUpdateFromExternalAuth($userData);

            // Create Sanctum token
            $token = $user->createToken('mobile')->plainTextToken;

            Log::info('User authenticated via Firebase', [
                'user_id' => $user->id,
                'firebase_uid' => $firebaseUser->sub ?? null,
            ]);

            return response()->json([
                'message' => 'Authentication successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified' => $user->email_verified_at !== null,
                ],
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            Log::error('Auth exchange failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Authentication failed'], 500);
        }
    }

    /**
     * Map Firebase user claims to our user data structure
     */
    private function mapFirebaseToUserData(object $firebaseUser): array
    {
        return [
            'firebase_uid' => $firebaseUser->sub ?? $firebaseUser->user_id ?? null,
            'email' => $firebaseUser->email ?? null,
            'name' => $firebaseUser->name ?? $firebaseUser->email ?? 'Unknown User',
            'email_verified' => ($firebaseUser->email_verified ?? false) === true,
            'avatar_url' => $firebaseUser->picture ?? null,
        ];
    }

    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users'],
            'password' => ['required', Password::defaults()],
        ]);

        try {
            $user = $this->userService->createUser($validated);
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'User registered successfully',
                'user' => $user,
                'token' => $token,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Login user
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        // Update last active timestamp
        $this->userService->updateLastActive($user);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Get current user
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Update last active timestamp
        $this->userService->updateLastActive($user);

        return response()->json([
            'user' => $user->load(['groups']),
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Revoke current token
        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Logged out from all devices',
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required'],
            'new_password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
                'errors' => ['current_password' => ['Current password is incorrect']],
            ], 422);
        }

        $this->userService->updateUser($user, [
            'password' => $validated['new_password'],
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Update user settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.theme' => ['nullable', 'string', 'in:light,dark,system'],
            'settings.notifications' => ['nullable', 'boolean'],
            'settings.turn_reminders' => ['nullable', 'boolean'],
            'settings.auto_skip_timeout' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $updatedUser = $this->userService->updateUserSettings($user, $validated['settings']);

        return response()->json([
            'message' => 'Settings updated successfully',
            'user' => $updatedUser,
        ]);
    }
}
