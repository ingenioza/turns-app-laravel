<?php

namespace App\Infrastructure\Repositories;

use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return User::find($id);
    }
    
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }
    
    public function findByUsername(string $username): ?User
    {
        return User::where('username', $username)->first();
    }
    
    public function create(array $data): User
    {
        return User::create($data);
    }
    
    public function update(User $user, array $data): bool
    {
        return $user->update($data);
    }
    
    public function delete(User $user): bool
    {
        return $user->delete();
    }
    
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return User::paginate($perPage);
    }
    
    public function findActiveUsers(): array
    {
        return User::where('status', 'active')->get()->toArray();
    }
    
    public function findByStatus(string $status): array
    {
        return User::where('status', $status)->get()->toArray();
    }
    
    public function search(string $query): array
    {
        return User::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->orWhere('username', 'like', "%{$query}%")
            ->get()
            ->toArray();
    }
    
    public function findRecentlyActive(int $days = 30): array
    {
        return User::where('last_active_at', '>=', now()->subDays($days))
            ->orderBy('last_active_at', 'desc')
            ->get()
            ->toArray();
    }
}
