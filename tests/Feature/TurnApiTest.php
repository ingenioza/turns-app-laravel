<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TurnApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_start_turn_in_group()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $user->id, 'status' => 'active']);
        $group->members()->attach($user->id, ['role' => 'admin', 'is_active' => true, 'turn_order' => 1]);
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/turns', [
            'group_id' => $group->id,
            'notes' => 'Starting my turn',
            'metadata' => ['device' => 'mobile'],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'turn' => [
                    'id',
                    'group_id',
                    'user_id',
                    'status',
                    'started_at',
                    'notes',
                    'metadata',
                ],
            ]);

        $this->assertDatabaseHas('turns', [
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'active',
            'notes' => 'Starting my turn',
        ]);
    }

    public function test_cannot_start_turn_if_not_group_member()
    {
        $user = User::factory()->create();
        $creator = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $creator->id, 'status' => 'active']);
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/turns', [
            'group_id' => $group->id,
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'You are not a member of this group']);
    }

    public function test_cannot_start_turn_if_active_turn_exists()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $user1->id, 'status' => 'active']);
        
        $group->members()->attach($user1->id, ['role' => 'admin', 'is_active' => true, 'turn_order' => 1]);
        $group->members()->attach($user2->id, ['role' => 'member', 'is_active' => true, 'turn_order' => 2]);
        
        // Create active turn for user1
        Turn::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user1->id,
            'status' => 'active',
        ]);
        
        Sanctum::actingAs($user2);

        $response = $this->postJson('/api/turns', [
            'group_id' => $group->id,
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'There is already an active turn in this group']);
    }

    public function test_user_can_complete_their_turn()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $user->id, 'status' => 'active']);
        $group->members()->attach($user->id, ['role' => 'admin', 'is_active' => true, 'turn_order' => 1]);
        
        $turn = Turn::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'active',
            'started_at' => now()->subMinutes(30),
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/turns/{$turn->id}/complete", [
            'notes' => 'Completed successfully',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Turn completed successfully']);


        $this->assertDatabaseHas('turns', [
            'id' => $turn->id,
            'status' => 'completed',
            'notes' => 'Completed successfully',
        ]);

    $turn->refresh();
        $this->assertNotNull($turn->ended_at);
        $this->assertNotNull($turn->duration_seconds);
    }

    public function test_admin_can_complete_any_turn()
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $admin->id, 'status' => 'active']);
        
        $group->members()->attach($admin->id, ['role' => 'admin', 'is_active' => true, 'turn_order' => 1]);
        $group->members()->attach($member->id, ['role' => 'member', 'is_active' => true, 'turn_order' => 2]);
        
        $turn = Turn::create([
            'group_id' => $group->id,
            'user_id' => $member->id,
            'status' => 'active',
            'started_at' => now()->subMinutes(30),
        ]);
        
        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/turns/{$turn->id}/complete");

        $response->assertStatus(200);
        $this->assertDatabaseHas('turns', [
            'id' => $turn->id,
            'status' => 'completed',
        ]);
    }

    public function test_user_can_skip_their_turn()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $user->id, 'status' => 'active']);
        $group->members()->attach($user->id, ['role' => 'admin', 'is_active' => true, 'turn_order' => 1]);
        
        $turn = Turn::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'active',
            'started_at' => now()->subMinutes(5),
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/turns/{$turn->id}/skip", [
            'reason' => 'Not feeling well',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Turn skipped successfully']);

        $this->assertDatabaseHas('turns', [
            'id' => $turn->id,
            'status' => 'skipped',
            'notes' => 'Not feeling well',
        ]);
    }

    public function test_admin_can_force_end_turn()
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $admin->id, 'status' => 'active']);
        
        $group->members()->attach($admin->id, ['role' => 'admin', 'is_active' => true, 'turn_order' => 1]);
        $group->members()->attach($member->id, ['role' => 'member', 'is_active' => true, 'turn_order' => 2]);
        
        $turn = Turn::create([
            'group_id' => $group->id,
            'user_id' => $member->id,
            'status' => 'active',
            'started_at' => now()->subHour(),
        ]);
        
        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/turns/{$turn->id}/force-end", [
            'reason' => 'Turn took too long',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Turn force-ended successfully']);

        $this->assertDatabaseHas('turns', [
            'id' => $turn->id,
            'status' => 'expired',
            'notes' => 'Turn took too long',
        ]);
    }

    public function test_can_get_active_turn_for_group()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $user->id, 'status' => 'active']);
        $group->members()->attach($user->id, ['role' => 'admin', 'is_active' => true, 'turn_order' => 1]);
        
        $turn = Turn::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'active',
            'started_at' => now()->subMinutes(10),
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/groups/{$group->id}/turns/active");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'active_turn' => [
                    'id',
                    'status',
                    'started_at',
                    'user',
                ],
            ]);
    }

    public function test_can_get_current_turn_info_for_group()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $user1->id, 'status' => 'active']);
        
        $group->members()->attach($user1->id, ['role' => 'admin', 'is_active' => true, 'turn_order' => 1]);
        $group->members()->attach($user2->id, ['role' => 'member', 'is_active' => true, 'turn_order' => 2]);
        
        Sanctum::actingAs($user1);

        $response = $this->getJson("/api/groups/{$group->id}/turns/current");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'active_turn',
                'next_user' => [
                    'id',
                    'name',
                    'turn_order',
                ],
                'group_members',
            ]);
    }

    public function test_can_get_turn_history_for_group()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $user->id, 'status' => 'active']);
        $group->members()->attach($user->id, ['role' => 'admin', 'is_active' => true, 'turn_order' => 1]);
        
        // Create some completed turns
        Turn::factory()->count(3)->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'completed',
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/groups/{$group->id}/turns/history");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_get_user_stats()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $user->id, 'status' => 'active']);
        $group->members()->attach($user->id, ['role' => 'admin', 'is_active' => true, 'turn_order' => 1]);
        
        // Create various turn statuses
        Turn::factory()->create(['user_id' => $user->id, 'status' => 'completed', 'duration_seconds' => 1800]);
        Turn::factory()->create(['user_id' => $user->id, 'status' => 'skipped']);
        Turn::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/turns/user-stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user_stats' => [
                    'total_turns',
                    'completed_turns',
                    'skipped_turns',
                    'active_turns',
                    'total_duration_seconds',
                    'average_duration_seconds',
                    'total_duration_formatted',
                    'average_duration_formatted',
                ],
            ]);
    }

    public function test_can_get_group_stats()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $user->id, 'status' => 'active']);
        $group->members()->attach($user->id, ['role' => 'admin', 'is_active' => true, 'turn_order' => 1]);
        
        Turn::factory()->count(5)->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'completed',
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/groups/{$group->id}/turns/stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'group_stats' => [
                    'total_turns',
                    'completed_turns',
                    'total_members',
                    'active_members',
                ],
                'member_stats',
            ]);
    }

    public function test_user_can_list_their_turns()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $user->id, 'status' => 'active']);
        $group->members()->attach($user->id, ['role' => 'admin', 'is_active' => true, 'turn_order' => 1]);
        
        Turn::factory()->count(3)->create(['user_id' => $user->id, 'group_id' => $group->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/turns');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_view_turn_details()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $user->id, 'status' => 'active']);
        $group->members()->attach($user->id, ['role' => 'admin', 'is_active' => true, 'turn_order' => 1]);
        
        $turn = Turn::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/turns/{$turn->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'turn' => [
                    'id',
                    'group_id',
                    'user_id',
                    'status',
                    'started_at',
                ],
            ]);
    }
}
