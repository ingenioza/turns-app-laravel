<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GroupWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create permissions if they don't exist
        Permission::firstOrCreate(['name' => 'groups.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'groups.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'groups.join', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'groups.update', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'groups.delete', 'guard_name' => 'web']);
        
        // Create a test user and authenticate with Sanctum
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);
        
        // Give the user necessary permissions
        $this->user->givePermissionTo([
            'groups.view',
            'groups.create',
            'groups.join',
            'groups.update',
            'groups.delete'
        ]);
        
        // Use Sanctum authentication instead of Laravel's default auth
        Sanctum::actingAs($this->user);
    }

    public function test_can_view_groups_index(): void
    {
        // Create a group where user is creator
        $userGroup = Group::factory()->create(['creator_id' => $this->user->id]);
        $userGroup->members()->attach($this->user->id, [
            'role' => 'admin',
            'turn_order' => 1,
            'joined_at' => now()
        ]);

        $response = $this->get('/groups');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Groups/Index')
                ->has('groups', 1) // Start with just checking for 1 group
        );
    }

    public function test_can_view_create_group_form(): void
    {
        $response = $this->get('/groups/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Groups/Create')
        );
    }

    public function test_can_create_group_via_web(): void
    {
        $groupData = [
            'name' => 'Test Web Group',
            'description' => 'A group created via web interface'
        ];

        $response = $this->post('/groups', $groupData);

        $response->assertRedirect(); // Redirect to the show page
        $response->assertSessionHas('success', 'Group created successfully!');

        $this->assertDatabaseHas('groups', [
            'name' => 'Test Web Group',
            'description' => 'A group created via web interface',
            'creator_id' => $this->user->id
        ]);
    }

    public function test_can_view_join_group_form(): void
    {
        $response = $this->get('/groups/join');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Groups/Join')
        );
    }

    public function test_can_join_group_via_web(): void
    {
        // Create a group by another user with active status
        $otherUser = User::factory()->create();
        $group = Group::factory()->create([
            'creator_id' => $otherUser->id,
            'status' => 'active'  // Ensure it's active
        ]);
        
        // Attach creator to the group first
        $group->members()->attach($otherUser->id, [
            'role' => 'admin',
            'turn_order' => 1,
            'joined_at' => now()
        ]);

        $response = $this->post('/groups/join', [
            'invite_code' => $group->invite_code
        ]);

        $response->assertRedirect(); // Redirects after joining
        $response->assertSessionHas('success', 'Successfully joined the group!');

        $this->assertTrue($group->members()->where('user_id', $this->user->id)->exists());
    }

    public function test_can_view_group_details(): void
    {
        // Create a group and attach the user
        $group = Group::factory()->create(['creator_id' => $this->user->id]);
        $group->members()->attach($this->user->id, [
            'role' => 'admin',
            'turn_order' => 1,
            'joined_at' => now()
        ]);

        $response = $this->get("/groups/{$group->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Groups/Show')
                ->has('group')  // Just check that group exists first
        );
    }

    public function test_cannot_view_group_details_if_not_member(): void
    {
        // Create a group by another user
        $otherUser = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $otherUser->id]);

        $response = $this->get("/groups/{$group->id}");

        $response->assertStatus(403);
    }

    public function test_validation_errors_are_handled_properly(): void
    {
        // Test empty group name
        $response = $this->post('/groups', [
            'name' => '',
            'description' => 'Test description'
        ]);

        $response->assertSessionHasErrors(['name']);

        // Test invalid invite code
        $response = $this->post('/groups/join', [
            'invite_code' => 'invalid-code'
        ]);

        $response->assertSessionHasErrors(['invite_code']);
    }

    public function test_middleware_protects_routes(): void
    {
        // Create a new test case without authentication
        $this->app->make('auth')->forgetGuards();

        $routes = [
            '/groups',
            '/groups/create', 
            '/groups/join'
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertRedirect('/login');
        }
    }
}
