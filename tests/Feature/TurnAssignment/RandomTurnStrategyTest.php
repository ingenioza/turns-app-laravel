<?php

use App\Application\Services\TurnAssignment\RandomTurnStrategy;
use App\Models\Group;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->strategy = new RandomTurnStrategy;

    $this->group = Group::factory()->create();
    $this->users = User::factory(3)->create();

    // Attach users to group
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

test('returns random user from group members', function () {
    $result = $this->strategy->getNextUser($this->group);

    expect($result)->toBeInstanceOf(User::class);
    expect($this->users->pluck('id'))->toContain($result->id);
});

test('excludes user with active turn by default', function () {
    $activeUser = $this->users->first();

    // Create active turn for first user
    Turn::factory()->create([
        'group_id' => $this->group->id,
        'user_id' => $activeUser->id,
        'status' => 'active',
    ]);

    // Run multiple times to ensure exclusion
    for ($i = 0; $i < 10; $i++) {
        $result = $this->strategy->getNextUser($this->group);
        expect($result->id)->not->toBe($activeUser->id);
    }
});

test('includes user with active turn when configured', function () {
    $activeUser = $this->users->first();

    Turn::factory()->create([
        'group_id' => $this->group->id,
        'user_id' => $activeUser->id,
        'status' => 'active',
    ]);

    $this->strategy->setConfiguration(['exclude_current_user' => false]);

    $result = $this->strategy->getNextUser($this->group);
    expect($this->users->pluck('id'))->toContain($result->id);
});

test('returns null when all members excluded', function () {
    // Only one member in group
    $singleUserGroup = Group::factory()->create();
    $user = User::factory()->create();

    $singleUserGroup->members()->attach($user->id, [
        'role' => 'member',
        'turn_order' => 1,
        'joined_at' => now(),
    ]);

    // Create active turn for the only user
    Turn::factory()->create([
        'group_id' => $singleUserGroup->id,
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    $result = $this->strategy->getNextUser($singleUserGroup);

    expect($result)->toBeNull();
});

test('is reproducible with seed', function () {
    $this->strategy->setConfiguration(['seed' => 12345]);

    $firstResult = $this->strategy->getNextUser($this->group);

    // Reset strategy with same seed
    $newStrategy = new RandomTurnStrategy;
    $newStrategy->setConfiguration(['seed' => 12345]);

    $secondResult = $newStrategy->getNextUser($this->group);

    expect($firstResult->id)->toBe($secondResult->id);
});

test('produces different results with different seeds', function () {
    $this->strategy->setConfiguration(['seed' => 12345]);
    $firstResult = $this->strategy->getNextUser($this->group);

    $this->strategy->setConfiguration(['seed' => 54321]);
    $secondResult = $this->strategy->getNextUser($this->group);

    // With different seeds, results should be different (high probability)
    expect($firstResult->id)->not->toBe($secondResult->id);
});

test('has correct metadata', function () {
    expect($this->strategy->getName())->toBe('random');
    expect($this->strategy->getDescription())->toContain('Randomly selects');
    expect($this->strategy->getConfiguration())->toBeArray();
    expect($this->strategy->getConfiguration())->toHaveKey('exclude_current_user');
});
