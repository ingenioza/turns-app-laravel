<?php

use App\Application\Services\TurnAssignment\RandomTurnStrategy;
use App\Application\Services\TurnAssignment\RoundRobinTurnStrategy;
use App\Application\Services\TurnAssignment\TurnAssignmentService;
use App\Application\Services\TurnAssignment\WeightedTurnStrategy;
use App\Models\Group;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurnAssignmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    private $creator;

    private $group;

    private $users;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a creator user
        $this->creator = new User([
            'name' => 'Test Creator',
            'email' => 'creator@test.com',
            'password' => 'password123',
        ]);
        $this->creator->save();

        // Create a test group
        $this->group = new Group([
            'name' => 'Test Group',
            'description' => 'Test Description',
            'creator_id' => $this->creator->id,
            'status' => 'active',
            'invite_code' => 'TEST123',
            'settings' => [
                'turn_assignment_strategy' => 'random',
                'turn_assignment_config' => ['seed' => 123],
            ],
        ]);
        $this->group->save();

        // Create test users
        $this->users = collect();
        for ($i = 1; $i <= 3; $i++) {
            $user = new User([
                'name' => "Test User $i",
                'email' => "user$i@test.com",
                'password' => 'password123',
            ]);
            $user->save();

            // Attach to group as member
            $this->group->members()->attach($user->id, [
                'role' => 'member',
                'turn_order' => $i,
                'joined_at' => now(),
            ]);

            $this->users->push($user);
        }
    }

    public function test_random_strategy_metadata()
    {
        $strategy = new RandomTurnStrategy;

        $this->assertEquals('random', $strategy->getName());
        $this->assertEquals('Randomly selects the next user from eligible group members', $strategy->getDescription());
    }

    public function test_random_strategy_returns_null_for_empty_group()
    {
        $strategy = new RandomTurnStrategy;
        $emptyGroup = new Group([
            'name' => 'Empty Group',
            'description' => 'No members',
            'creator_id' => $this->creator->id,
            'status' => 'active',
            'invite_code' => 'EMPTY1',
            'settings' => [],
        ]);
        $emptyGroup->save();

        $result = $strategy->getNextUser($emptyGroup);

        $this->assertNull($result);
    }

    public function test_random_strategy_returns_user_from_group()
    {
        $strategy = new RandomTurnStrategy;
        $strategy->setConfiguration(['seed' => 123]);

        $result = $strategy->getNextUser($this->group);

        $this->assertNotNull($result);
        $this->assertInstanceOf(User::class, $result);
        $this->assertTrue($this->users->contains('id', $result->id));
    }

    public function test_round_robin_strategy_metadata()
    {
        $strategy = new RoundRobinTurnStrategy;

        $this->assertEquals('round_robin', $strategy->getName());
        $this->assertEquals('Cycles through group members in order based on their turn_order', $strategy->getDescription());
    }

    public function test_round_robin_strategy_returns_null_for_empty_group()
    {
        $strategy = new RoundRobinTurnStrategy;
        $emptyGroup = new Group([
            'name' => 'Empty Group',
            'description' => 'No members',
            'creator_id' => $this->creator->id,
            'status' => 'active',
            'invite_code' => 'EMPTY2',
            'settings' => [],
        ]);
        $emptyGroup->save();

        $result = $strategy->getNextUser($emptyGroup);

        $this->assertNull($result);
    }

    public function test_round_robin_strategy_cycles_through_users()
    {
        $strategy = new RoundRobinTurnStrategy;

        // First call should return user with turn_order 1
        $result1 = $strategy->getNextUser($this->group);
        $this->assertEquals(1, $result1->pivot->turn_order);

        // Create a turn for user 1
        $turn = new Turn([
            'group_id' => $this->group->id,
            'user_id' => $result1->id,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now()->subMinutes(30),
        ]);
        $turn->save();

        // Refresh the group to pick up the new turn
        $this->group->refresh();

        // Next call should return user with turn_order 2
        $result2 = $strategy->getNextUser($this->group);
        $this->assertEquals(2, $result2->pivot->turn_order);
    }

    public function test_weighted_strategy_metadata()
    {
        $strategy = new WeightedTurnStrategy;

        $this->assertEquals('weighted', $strategy->getName());
        $this->assertEquals('Assigns turns based on weighted factors: time since last turn, completion rate, and skip frequency', $strategy->getDescription());
    }

    public function test_weighted_strategy_returns_null_for_empty_group()
    {
        $strategy = new WeightedTurnStrategy;
        $emptyGroup = new Group([
            'name' => 'Empty Group',
            'description' => 'No members',
            'creator_id' => $this->creator->id,
            'status' => 'active',
            'invite_code' => 'EMPTY3',
            'settings' => [],
        ]);
        $emptyGroup->save();

        $result = $strategy->getNextUser($emptyGroup);

        $this->assertNull($result);
    }

    public function test_weighted_strategy_returns_user_from_group()
    {
        $strategy = new WeightedTurnStrategy;
        $strategy->setConfiguration([
            'time_weight' => 0.5,
            'completion_rate_weight' => 0.3,
            'skip_frequency_weight' => 0.2,
            'seed' => 123,
        ]);

        $result = $strategy->getNextUser($this->group);

        $this->assertNotNull($result);
        $this->assertInstanceOf(User::class, $result);
        $this->assertTrue($this->users->contains('id', $result->id));
    }

    public function test_turn_assignment_service_uses_default_strategy()
    {
        $service = new TurnAssignmentService;

        $result = $service->getNextUser($this->group);

        $this->assertNotNull($result);
        $this->assertInstanceOf(User::class, $result);
        $this->assertTrue($this->users->contains('id', $result->id));
    }

    public function test_turn_assignment_service_uses_group_strategy_preference()
    {
        $service = new TurnAssignmentService;

        // Update group to use round-robin strategy
        $this->group->update([
            'settings' => [
                'turn_assignment_strategy' => 'round_robin',
                'turn_assignment_config' => [],
            ],
        ]);

        $result = $service->getNextUserWithStrategy($this->group, 'round_robin');

        $this->assertNotNull($result);
        $this->assertInstanceOf(User::class, $result);
        // Should return first user in turn order
        $this->assertEquals(1, $result->pivot->turn_order);
    }

    public function test_turn_assignment_service_fallback_to_default()
    {
        $service = new TurnAssignmentService;

        // Test with invalid strategy should throw exception
        $this->expectException(\InvalidArgumentException::class);
        $service->getNextUserWithStrategy($this->group, 'invalid-strategy');
    }
}
