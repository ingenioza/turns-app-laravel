<?php

namespace Tests\Unit;

use App\Models\Group;
use App\Models\User;
use App\Models\Turn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_can_be_created()
    {
        $user = User::factory()->create();
        
        $group = Group::create([
            'name' => 'Test Group',
            'description' => 'A test group',
            'creator_id' => $user->id,
            'status' => 'active'
        ]);

        $this->assertDatabaseHas('groups', [
            'name' => 'Test Group',
            'creator_id' => $user->id,
            'status' => 'active'
        ]);

        $this->assertNotNull($group->invite_code);
        $this->assertEquals(8, strlen($group->invite_code));
    }

    public function test_group_belongs_to_creator()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $user->id]);

        $this->assertEquals($user->id, $group->creator->id);
    }

    public function test_group_can_have_members()
    {
        $creator = User::factory()->create();
        $member = User::factory()->create();
        
        $group = Group::factory()->create(['creator_id' => $creator->id]);
        
        $group->members()->attach($member->id, [
            'role' => 'member',
            'joined_at' => now(),
            'is_active' => true,
            'turn_order' => 1
        ]);

        $this->assertTrue($group->members->contains($member));
        $this->assertEquals('member', $group->members->first()->pivot->role);
        $this->assertEquals(1, $group->members->first()->pivot->is_active); // SQLite stores boolean as integer
    }

    public function test_group_can_have_turns()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $user->id]);
        
        $turn = Turn::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id
        ]);

        $this->assertTrue($group->turns->contains($turn));
    }

    public function test_group_generates_unique_invite_code()
    {
        $user = User::factory()->create();
        
        $group1 = Group::factory()->create(['creator_id' => $user->id]);
        $group2 = Group::factory()->create(['creator_id' => $user->id]);

        $this->assertNotEquals($group1->invite_code, $group2->invite_code);
    }

    public function test_active_scope_filters_active_groups()
    {
        $user = User::factory()->create();
        
        Group::factory()->create(['creator_id' => $user->id, 'status' => 'active']);
        Group::factory()->create(['creator_id' => $user->id, 'status' => 'inactive']);

        $activeGroups = Group::active()->get();
        
        $this->assertEquals(1, $activeGroups->count());
        $this->assertEquals('active', $activeGroups->first()->status);
    }
}
