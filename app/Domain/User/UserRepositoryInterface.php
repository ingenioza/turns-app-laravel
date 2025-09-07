<?php

namespace App\Domain\User;

use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findByUsername(string $username): ?User;

    public function findByFirebaseUid(string $firebaseUid): ?User;

    public function create(array $data): User;

    public function update(User $user, array $data): bool;

    public function delete(User $user): bool;

    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function findActiveUsers(): array;

    public function findByStatus(string $status): array;

    public function search(string $query): array;

    public function findRecentlyActive(int $days = 30): array;
}
