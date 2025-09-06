<?php

namespace App\Http\Controllers\Api;

use App\Application\Services\UserService;
use App\Domain\User\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Display a listing of active users
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'min:3'],
            'status' => ['nullable', 'string', 'in:active,inactive,suspended'],
        ]);

        if (!empty($validated['search'])) {
            $users = $this->userService->searchUsers($validated['search']);
        } elseif (!empty($validated['status'])) {
            $users = User::where('status', $validated['status'])->get()->toArray();
        } else {
            $users = $this->userService->getActiveUsers();
        }

        return response()->json([
            'users' => $users,
        ]);
    }

    /**
     * Display the specified user
     */
    public function show(Request $request, User $user): JsonResponse
    {
        // Users can view their own profile or any other user's basic info
        /** @var User $currentUser */
        $currentUser = $request->user();
        
        if ($currentUser->id === $user->id) {
            // Full profile for self
            return response()->json([
                'user' => $user->load(['groups']),
            ]);
        } else {
            // Basic info for others
            return response()->json([
                'user' => $user->makeHidden(['email', 'settings', 'is_admin']),
            ]);
        }
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        // Users can only update their own profile (unless admin)
        if ($currentUser->id !== $user->id && !$currentUser->is_admin) {
            return response()->json([
                'message' => 'Not authorized to update this user',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:255', 'unique:users,username,' . $user->id],
            'avatar_url' => ['nullable', 'string', 'max:500', 'url'],
        ]);

        try {
            $updatedUser = $this->userService->updateUser($user, $validated);

            return response()->json([
                'message' => 'User updated successfully',
                'user' => $updatedUser,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Remove the specified user (soft delete)
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        // Users can only delete their own account (unless admin)
        if ($currentUser->id !== $user->id && !$currentUser->is_admin) {
            return response()->json([
                'message' => 'Not authorized to delete this user',
            ], 403);
        }

        try {
            $this->userService->suspendUser($user, 'Account deactivated by user');

            return response()->json([
                'message' => 'User account deactivated successfully',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get user's groups
     */
    public function groups(Request $request, User $user): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        // Users can only view their own groups (unless admin or shared groups)
        if ($currentUser->id !== $user->id && !$currentUser->is_admin) {
            // Show only shared groups
            $sharedGroups = $currentUser->groups()
                ->whereHas('members', function ($query) use ($user) {
                    $query->where('users.id', $user->id);
                })
                ->get();

            return response()->json([
                'groups' => $sharedGroups,
            ]);
        }

        return response()->json([
            'groups' => $user->groups,
        ]);
    }

    /**
     * Get recently active users
     */
    public function recentlyActive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $days = $validated['days'] ?? 30;
        $users = $this->userService->getRecentlyActiveUsers($days);

        return response()->json([
            'users' => $users,
        ]);
    }

    /**
     * Update user settings
     */
    public function updateSettings(Request $request, User $user): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        // Users can only update their own settings
        if ($currentUser->id !== $user->id) {
            return response()->json([
                'message' => 'Not authorized to update user settings',
            ], 403);
        }

        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.theme' => ['nullable', 'string', 'in:light,dark,system'],
            'settings.notifications' => ['nullable', 'boolean'],
            'settings.turn_reminders' => ['nullable', 'boolean'],
            'settings.auto_skip_timeout' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        try {
            $updatedUser = $this->userService->updateUserSettings($user, $validated['settings']);

            return response()->json([
                'message' => 'Settings updated successfully',
                'user' => $updatedUser,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Search users
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:3'],
        ]);

        $users = $this->userService->searchUsers($validated['query']);

        return response()->json([
            'users' => array_map(function ($user) {
                // Remove sensitive information
                unset($user['email'], $user['settings'], $user['is_admin']);
                return $user;
            }, $users),
        ]);
    }
}
