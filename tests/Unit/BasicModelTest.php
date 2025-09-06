<?php

namespace Tests\Unit;

use App\Models\Group;
use App\Models\User;
use App\Models\Turn;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BasicModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_exist()
    {
        $this->assertTrue(class_exists(User::class));
        $this->assertTrue(class_exists(Group::class));
        $this->assertTrue(class_exists(Turn::class));
    }

    public function test_user_can_create_group()
    {
        $user = User::factory()->create();
        
        $group = new Group([
            'name' => 'Test Group',
            'description' => 'A test group',
            'creator_id' => $user->id,
            'status' => 'active'
        ]);
        
        $group->save();
        
        $this->assertDatabaseHas('groups', [
            'name' => 'Test Group',
            'creator_id' => $user->id
        ]);
    }
}
