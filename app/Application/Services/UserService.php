<?php

namespace App\Application\Services;

use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function createOrUpdateFromExternalAuth(array $data): User
    {
        // Find user by external ID first, then by email
        $user = null;

        if (! empty($data['firebase_uid'])) {
            $user = $this->userRepository->findByFirebaseUid($data['firebase_uid']);
        }

        if (! $user && ! empty($data['email'])) {
            $user = $this->userRepository->findByEmail($data['email']);
        }

        if ($user) {
            // Update existing user with new external auth data
            $updateData = [];

            if (! empty($data['firebase_uid']) && $user->firebase_uid !== $data['firebase_uid']) {
                $updateData['firebase_uid'] = $data['firebase_uid'];
            }

            if (! empty($data['name']) && $user->name !== $data['name']) {
                $updateData['name'] = $data['name'];
            }

            if (! empty($data['avatar_url']) && $user->avatar_url !== $data['avatar_url']) {
                $updateData['avatar_url'] = $data['avatar_url'];
            }

            if ($data['email_verified'] === true && $user->email_verified_at === null) {
                $updateData['email_verified_at'] = now();
            }

            // Update last active
            $updateData['last_active_at'] = now();

            if (! empty($updateData)) {
                $this->userRepository->update($user, $updateData);
                $user = $user->fresh();
            }

            return $user;
        }

        // Create new user from external auth
        $userData = [
            'firebase_uid' => $data['firebase_uid'] ?? null,
            'email' => $data['email'],
            'name' => $data['name'] ?? $data['email'] ?? 'Unknown User',
            'email_verified_at' => $data['email_verified'] === true ? now() : null,
            'avatar_url' => $data['avatar_url'] ?? null,
            'last_active_at' => now(),
            'status' => 'active',
        ];

        // Generate unique username
        $userData['username'] = $this->generateUniqueUsername($userData['name']);

        return $this->userRepository->create($userData);
    }

    public function createUser(array $data): User
    {
        // Validate required fields
        $this->validateUserData($data);

        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Generate unique username if not provided
        if (empty($data['username'])) {
            $data['username'] = $this->generateUniqueUsername($data['name']);
        }

        return $this->userRepository->create($data);
    }

    public function updateUser(User $user, array $data): User
    {
        // Remove sensitive fields that shouldn't be updated directly
        unset($data['password'], $data['email_verified_at']);

        // Hash password if being updated
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $this->userRepository->update($user, $data);

        return $user->fresh();
    }

    public function updateLastActive(User $user): void
    {
        $this->userRepository->update($user, [
            'last_active_at' => now(),
        ]);
    }

    public function updateUserSettings(User $user, array $settings): User
    {
        $currentSettings = $user->getSettings();
        $newSettings = array_merge($currentSettings, $settings);

        $this->userRepository->update($user, [
            'settings' => $newSettings,
        ]);

        return $user->fresh();
    }

    public function suspendUser(User $user, ?string $reason = null): User
    {
        $this->userRepository->update($user, [
            'status' => 'suspended',
        ]);

        // Log the suspension
        activity()
            ->performedOn($user)
            ->withProperties(['reason' => $reason])
            ->log('user_suspended');

        return $user->fresh();
    }

    public function activateUser(User $user): User
    {
        $this->userRepository->update($user, [
            'status' => 'active',
        ]);

        // Log the activation
        activity()
            ->performedOn($user)
            ->log('user_activated');

        return $user->fresh();
    }

    public function searchUsers(string $query): array
    {
        return $this->userRepository->search($query);
    }

    public function getActiveUsers(): array
    {
        return $this->userRepository->findActiveUsers();
    }

    public function getRecentlyActiveUsers(int $days = 30): array
    {
        return $this->userRepository->findRecentlyActive($days);
    }

    private function validateUserData(array $data): void
    {
        if (empty($data['name'])) {
            throw ValidationException::withMessages(['name' => 'Name is required']);
        }

        if (empty($data['email'])) {
            throw ValidationException::withMessages(['email' => 'Email is required']);
        }

        if (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Invalid email format']);
        }

        // Check if email already exists
        if ($this->userRepository->findByEmail($data['email'])) {
            throw ValidationException::withMessages(['email' => 'Email already exists']);
        }

        // Check if username already exists (if provided)
        if (! empty($data['username']) && $this->userRepository->findByUsername($data['username'])) {
            throw ValidationException::withMessages(['username' => 'Username already exists']);
        }
    }

    private function generateUniqueUsername(string $name): string
    {
        $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
        $username = $baseUsername;
        $counter = 1;

        while ($this->userRepository->findByUsername($username)) {
            $username = $baseUsername.$counter;
            $counter++;
        }

        return $username;
    }
}
