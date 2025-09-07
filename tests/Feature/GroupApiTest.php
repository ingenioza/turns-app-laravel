<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_group()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/groups', [
            'name' => 'Test Group',
            'description' => 'A test group for testing',
            'settings' => [
                'turn_duration' => 30,
                'notifications_enabled' => true,
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'group' => [
                    'id',
                    'name',
                    'description',
                    'creator_id',
                    'invite_code',
                    'status',
                    'settings',
                ],
            ]);

        $this->assertDatabaseHas('groups', [
            'name' => 'Test Group',
            'creator_id' => $user->id,
            'status' => 'active',
        ]);

        // Check if creator was added as admin member
        $group = Group::where('name', 'Test Group')->first();
        $this->assertTrue($group->members->contains($user));
        $this->assertEquals('admin', $group->members->first()->pivot->role);
    }

    public function test_user_can_list_their_groups()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Create groups for the user
        $group1 = Group::factory()->create(['creator_id' => $user->id]);
        $group2 = Group::factory()->create(['creator_id' => $user->id]);

        // Create group for other user
        $otherGroup = Group::factory()->create(['creator_id' => $otherUser->id]);

        // Add user as member to their groups
        $group1->members()->attach($user->id, ['role' => 'admin', 'is_active' => true]);
        $group2->members()->attach($user->id, ['role' => 'admin', 'is_active' => true]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/groups');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_join_group_with_invite_code()
    {
        $creator = User::factory()->create();
        $joiner = User::factory()->create();

        $group = Group::factory()->create([
            'creator_id' => $creator->id,
            'status' => 'active', // Ensure group is active for joining
        ]);
        $group->members()->attach($creator->id, ['role' => 'admin', 'is_active' => true, 'turn_order' => 1]);

        // Ensure invite code is set
        if (empty($group->invite_code)) {
            $group->update(['invite_code' => $group->generateInviteCode()]);
        }

        Sanctum::actingAs($joiner);

        $response = $this->postJson('/api/groups/join', [
            'invite_code' => $group->invite_code,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Successfully joined group',
            ]);

        $this->assertTrue($group->fresh()->members->contains($joiner));

        $joinerMembership = $group->members()->where('user_id', $joiner->id)->first();
        $this->assertEquals('member', $joinerMembership->pivot->role);
        $this->assertEquals(2, $joinerMembership->pivot->turn_order);
    }

    public function test_user_cannot_join_group_with_invalid_invite_code()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/groups/join', [
            'invite_code' => 'INVALID1',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Invalid invite code',
            ]);
    }

    public function test_user_can_view_group_details_if_member()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $user->id]);
        $group->members()->attach($user->id, ['role' => 'admin', 'is_active' => true]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/groups/{$group->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'group' => [
                    'id',
                    'name',
                    'description',
                    'creator_id',
                    'invite_code',
                    'status',
                ],
            ]);
    }

    public function test_user_cannot_view_group_details_if_not_member()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/groups/{$group->id}");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Unauthorized',
            ]);
    }

    public function test_admin_can_update_group()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $user->id]);
        $group->members()->attach($user->id, ['role' => 'admin', 'is_active' => true]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/groups/{$group->id}", [
            'name' => 'Updated Group Name',
            'description' => 'Updated description',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Group updated successfully',
            ]);

        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'name' => 'Updated Group Name',
            'description' => 'Updated description',
        ]);
    }

    public function test_creator_can_delete_group()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/groups/{$group->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Group deleted successfully',
            ]);

        $this->assertSoftDeleted('groups', ['id' => $group->id]);
    }
}
