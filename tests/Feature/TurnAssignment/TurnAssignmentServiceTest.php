<?php

use App\Application\Services\TurnAssignment\TurnAssignmentService;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(TurnAssignmentService::class);

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

test('uses default strategy when group has no preference', function () {
    $result = $this->service->getNextUser($this->group);

    expect($result)->toBeInstanceOf(User::class);
    expect($this->users->pluck('id'))->toContain($result->id);
});

test('uses group preferred strategy', function () {
    // Set group to use round_robin strategy
    $this->group->settings = ['turn_strategy' => 'round_robin'];
    $this->group->save();

    $result = $this->service->getNextUser($this->group);

    // Should return first user in order (round_robin behavior)
    $expectedUser = $this->group->activeMembers->firstWhere('pivot.turn_order', 1);
    expect($result->id)->toBe($expectedUser->id);
});

test('uses specific strategy with getNextUserWithStrategy', function () {
    $result = $this->service->getNextUserWithStrategy($this->group, 'round_robin');

    $expectedUser = $this->group->activeMembers->firstWhere('pivot.turn_order', 1);
    expect($result->id)->toBe($expectedUser->id);
});

test('passes configuration to strategy', function () {
    $config = ['seed' => 12345];

    $result1 = $this->service->getNextUserWithStrategy($this->group, 'random', $config);
    $result2 = $this->service->getNextUserWithStrategy($this->group, 'random', $config);

    // With same seed, should get same result
    expect($result1->id)->toBe($result2->id);
});

test('falls back to default strategy on error', function () {
    // Set group to use non-existent strategy
    $this->group->settings = ['turn_strategy' => 'non_existent'];
    $this->group->save();

    // Should fall back to default strategy
    $result = $this->service->getNextUser($this->group);
    expect($result)->toBeInstanceOf(User::class);
});

test('throws exception for unknown strategy in getNextUserWithStrategy', function () {
    expect(fn () => $this->service->getNextUserWithStrategy($this->group, 'unknown'))
        ->toThrow(InvalidArgumentException::class);
});

test('returns available strategies', function () {
    $strategies = $this->service->getAvailableStrategies();

    expect($strategies)->toBeArray();
    expect($strategies)->toHaveCount(3);

    $names = array_column($strategies, 'name');
    expect($names)->toContain('random');
    expect($names)->toContain('round_robin');
    expect($names)->toContain('weighted');

    foreach ($strategies as $strategy) {
        expect($strategy)->toHaveKeys(['name', 'description', 'configuration']);
    }
});

test('can get specific strategy instance', function () {
    $strategy = $this->service->getStrategy('random');

    expect($strategy->getName())->toBe('random');
});

test('throws exception for unknown strategy in getStrategy', function () {
    expect(fn () => $this->service->getStrategy('unknown'))
        ->toThrow(InvalidArgumentException::class);
});

test('can set default strategy', function () {
    $this->service->setDefaultStrategy('round_robin');

    // Group with no preference should now use round_robin
    $result = $this->service->getNextUser($this->group);
    $expectedUser = $this->group->activeMembers->firstWhere('pivot.turn_order', 1);

    expect($result->id)->toBe($expectedUser->id);
});

test('throws exception when setting unknown default strategy', function () {
    expect(fn () => $this->service->setDefaultStrategy('unknown'))
        ->toThrow(InvalidArgumentException::class);
});

test('can register custom strategy', function () {
    $customStrategy = new class implements \App\Application\Services\TurnAssignment\TurnAssignmentStrategyInterface
    {
        public function getNextUser(\App\Models\Group $group): ?\App\Models\User
        {
            return $group->activeMembers->last();
        }

        public function getName(): string
        {
            return 'custom';
        }

        public function getDescription(): string
        {
            return 'Custom test strategy';
        }

        public function getConfiguration(): array
        {
            return [];
        }

        public function setConfiguration(array $config): self
        {
            return $this;
        }
    };

    $this->service->registerStrategy($customStrategy);

    $strategies = $this->service->getAvailableStrategies();
    $names = array_column($strategies, 'name');

    expect($names)->toContain('custom');
});
