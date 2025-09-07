<?php

use App\Application\Services\TurnAssignment\RoundRobinTurnStrategy;
use App\Models\Group;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->strategy = new RoundRobinTurnStrategy;

    $this->group = Group::factory()->create();
    $this->users = User::factory(3)->create();

    // Attach users to group with specific turn orders
    foreach ($this->users as $index => $user) {
        $this->group->members()->attach($user->id, [
            'role' => 'member',
            'turn_order' => $index + 1,
            'joined_at' => now(),
        ]);
    }
});

test('returns null for group with no members', function () {
    $emptyGroup = Group::factory()->create();

    $result = $this->strategy->getNextUser($emptyGroup);

    expect($result)->toBeNull();
});

test('returns first user when no previous turns', function () {
    $result = $this->strategy->getNextUser($this->group);

    // Should return user with turn_order = 1
    $expectedUser = $this->group->activeMembers->firstWhere('pivot.turn_order', 1);

    expect($result->id)->toBe($expectedUser->id);
});

test('cycles through users in order', function () {
    // First turn - should get user with order 1
    $firstUser = $this->strategy->getNextUser($this->group);
    expect($firstUser->pivot->turn_order)->toBe(1);

    // Complete first turn
    Turn::factory()->create([
        'group_id' => $this->group->id,
        'user_id' => $firstUser->id,
        'status' => 'completed',
    ]);

    // Second turn - should get user with order 2
    $secondUser = $this->strategy->getNextUser($this->group);
    expect($secondUser->pivot->turn_order)->toBe(2);

    // Complete second turn
    Turn::factory()->create([
        'group_id' => $this->group->id,
        'user_id' => $secondUser->id,
        'status' => 'completed',
    ]);

    // Third turn - should get user with order 3
    $thirdUser = $this->strategy->getNextUser($this->group);
    expect($thirdUser->pivot->turn_order)->toBe(3);
});

test('cycles back to first user after all have had turns', function () {
    // Complete turns for all users
    foreach ($this->users as $user) {
        Turn::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'updated_at' => now()->addMinutes($user->pivot->turn_order),
        ]);
    }

    // Next turn should cycle back to first user
    $result = $this->strategy->getNextUser($this->group);
    $expectedUser = $this->group->activeMembers->firstWhere('pivot.turn_order', 1);

    expect($result->id)->toBe($expectedUser->id);
});

test('handles skipped turns correctly', function () {
    $firstUser = $this->group->activeMembers->firstWhere('pivot.turn_order', 1);

    // Skip first user's turn
    Turn::factory()->create([
        'group_id' => $this->group->id,
        'user_id' => $firstUser->id,
        'status' => 'skipped',
    ]);

    // Should proceed to next user in order
    $result = $this->strategy->getNextUser($this->group);
    expect($result->pivot->turn_order)->toBe(2);
});

test('starts from beginning when previous user no longer in group', function () {
    // Create a turn for a user not in current group
    $removedUser = User::factory()->create();
    Turn::factory()->create([
        'group_id' => $this->group->id,
        'user_id' => $removedUser->id,
        'status' => 'completed',
    ]);

    // Should start from first user since previous user is gone
    $result = $this->strategy->getNextUser($this->group);
    $expectedUser = $this->group->activeMembers->firstWhere('pivot.turn_order', 1);

    expect($result->id)->toBe($expectedUser->id);
});

test('respects configuration for cycle reset', function () {
    // Complete turns for all users
    foreach ($this->users as $user) {
        Turn::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'updated_at' => now()->addMinutes($user->pivot->turn_order),
        ]);
    }

    // Disable cycle reset
    $this->strategy->setConfiguration(['reset_on_cycle_complete' => false]);

    // Should return null instead of cycling
    $result = $this->strategy->getNextUser($this->group);
    expect($result)->toBeNull();
});

test('handles non-sequential turn orders', function () {
    // Create group with non-sequential orders
    $group = Group::factory()->create();
    $users = User::factory(3)->create();

    $orders = [5, 10, 15]; // Non-sequential
    foreach ($users as $index => $user) {
        $group->members()->attach($user->id, [
            'role' => 'member',
            'turn_order' => $orders[$index],
            'joined_at' => now(),
        ]);
    }

    // Should still follow order correctly
    $firstUser = $this->strategy->getNextUser($group);
    expect($firstUser->pivot->turn_order)->toBe(5);

    Turn::factory()->create([
        'group_id' => $group->id,
        'user_id' => $firstUser->id,
        'status' => 'completed',
    ]);

    $secondUser = $this->strategy->getNextUser($group);
    expect($secondUser->pivot->turn_order)->toBe(10);
});

test('has correct metadata', function () {
    expect($this->strategy->getName())->toBe('round_robin');
    expect($this->strategy->getDescription())->toContain('Cycles through');
    expect($this->strategy->getConfiguration())->toBeArray();
    expect($this->strategy->getConfiguration())->toHaveKey('reset_on_cycle_complete');
});
