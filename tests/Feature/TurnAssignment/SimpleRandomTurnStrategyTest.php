<?php

use App\Application\Services\TurnAssignment\RandomTurnStrategy;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleRandomTurnStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_strategy_has_correct_metadata()
    {
        $strategy = new RandomTurnStrategy;

        $this->assertEquals('random', $strategy->getName());
        $this->assertEquals('Randomly selects the next user from eligible group members', $strategy->getDescription());
    }

    public function test_returns_null_for_empty_group()
    {
        $strategy = new RandomTurnStrategy;

        // Create a user for the creator_id
        $creator = new User([
            'name' => 'Test Creator',
            'email' => 'creator@test.com',
            'password' => 'password123',
        ]);
        $creator->save();

        // Create a group without any members
        $group = new Group([
            'name' => 'Test Group',
            'description' => 'Test Description',
            'creator_id' => $creator->id,
            'status' => 'active',
            'invite_code' => 'TEST123',
            'settings' => [],
        ]);
        $group->save();

        $result = $strategy->getNextUser($group);

        $this->assertNull($result);
    }
}
