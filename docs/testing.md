# Laravel Backend Testing Strategy

## Testing Philosophy

Our testing approach follows the **Testing Pyramid** with emphasis on **Test-Driven Development (TDD)** and **95% code coverage** to ensure reliability, maintainability, and confidence in deployments.

### Testing Pyramid Structure

```
           /\
          /  \
         / E2E \      ← 10% - End-to-End (Browser Tests)
        /______\
       /        \
      / Integration\ ← 20% - Integration Tests (Feature Tests)
     /____________\
    /              \
   /  Unit Tests    \ ← 70% - Unit Tests (Models, Services, Actions)
  /__________________\
```

### Coverage Goals

- **Overall Coverage**: 95% minimum
- **Unit Tests**: 98% coverage
- **Feature Tests**: 90% coverage
- **Critical Paths**: 100% coverage (authentication, payments, data integrity)

## Testing Tools & Framework

### Core Testing Stack

#### Pest PHP
- **Purpose**: Modern testing framework with elegant syntax
- **Package**: `pestphp/pest ^2.0`
- **Features**:
  - Elegant and readable test syntax
  - Built-in Laravel support
  - Parallel test execution
  - Rich assertion library
  - Plugin ecosystem

#### Laravel Testing Framework
- **Built-in Features**:
  - HTTP testing with JSON assertions
  - Database testing with factories
  - Mock and fake implementations
  - Event and queue testing
  - Mail and notification testing

#### PHPUnit Integration
- **Purpose**: Underlying test runner with advanced features
- **Package**: `phpunit/phpunit ^10.0`
- **Configuration**:
  ```xml
  <!-- phpunit.xml -->
  <phpunit bootstrap="vendor/autoload.php"
           colors="true"
           processIsolation="false"
           stopOnFailure="false"
           cacheDirectory=".phpunit.cache">
      <testsuites>
          <testsuite name="Unit">
              <directory suffix="Test.php">./tests/Unit</directory>
          </testsuite>
          <testsuite name="Feature">
              <directory suffix="Test.php">./tests/Feature</directory>
          </testsuite>
      </testsuites>
      <coverage includeUncoveredFiles="true">
          <include>
              <directory suffix=".php">./app</directory>
          </include>
          <exclude>
              <directory suffix=".php">./app/Console</directory>
              <file>./app/Http/Kernel.php</file>
          </exclude>
      </coverage>
  </phpunit>
  ```

### Additional Testing Tools

#### Mockery
- **Purpose**: Powerful mocking framework
- **Package**: `mockery/mockery`
- **Features**:
  - Flexible mock objects
  - Partial mocks
  - Spy objects
  - Expectation assertions

#### Laravel Dusk (E2E)
- **Purpose**: Browser automation testing
- **Package**: `laravel/dusk`
- **Features**:
  - Chrome/Chromium automation
  - JavaScript testing
  - File upload testing
  - Screenshot on failure

## Test Structure & Organization

### Directory Structure

```
tests/
├── Feature/                          # Integration/Feature tests
│   ├── Api/                          # API endpoint tests
│   │   ├── GroupControllerTest.php   # Group API tests
│   │   ├── TurnControllerTest.php    # Turn API tests
│   │   └── AuthControllerTest.php    # Authentication tests
│   ├── Web/                          # Web interface tests
│   │   ├── GroupManagementTest.php   # Group web interface tests
│   │   └── DashboardTest.php         # Dashboard tests
│   ├── Auth/                         # Authentication flow tests
│   │   ├── LoginTest.php             # Login functionality
│   │   ├── RegistrationTest.php      # User registration
│   │   └── PasswordResetTest.php     # Password reset flow
│   └── Integration/                  # Service integration tests
│       ├── NotificationServiceTest.php
│       └── TurnAlgorithmServiceTest.php
├── Unit/                             # Unit tests
│   ├── Actions/                      # Action class tests
│   │   ├── Group/                    # Group action tests
│   │   │   ├── CreateGroupActionTest.php
│   │   │   ├── UpdateGroupActionTest.php
│   │   │   └── DeleteGroupActionTest.php
│   │   └── Turn/                     # Turn action tests
│   ├── Models/                       # Model tests
│   │   ├── GroupTest.php             # Group model tests
│   │   ├── ParticipantTest.php       # Participant model tests
│   │   └── TurnTest.php              # Turn model tests
│   ├── Services/                     # Service class tests
│   │   ├── TurnAlgorithmServiceTest.php
│   │   └── NotificationServiceTest.php
│   └── Policies/                     # Policy tests
│       ├── GroupPolicyTest.php
│       └── ParticipantPolicyTest.php
├── Browser/                          # Dusk E2E tests
│   ├── GroupManagementTest.php       # Full group workflow
│   ├── TurnExecutionTest.php         # Turn execution workflow
│   └── ResponsiveDesignTest.php      # Mobile responsiveness
└── Fixtures/                        # Test data and fixtures
    ├── groups.json                   # Sample group data
    ├── participants.json             # Sample participant data
    └── responses/                    # API response samples
        ├── group_created.json
        └── turn_executed.json
```

## Unit Testing

### Model Testing

```php
<?php

use App\Models\Group;
use App\Models\Participant;
use App\Models\User;

describe('Group Model', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->group = Group::factory()->create(['user_id' => $this->user->id]);
    });

    it('belongs to a user', function () {
        expect($this->group->user)->toBeInstanceOf(User::class);
        expect($this->group->user->id)->toBe($this->user->id);
    });

    it('has many participants', function () {
        Participant::factory(3)->create(['group_id' => $this->group->id]);
        
        expect($this->group->participants)->toHaveCount(3);
        expect($this->group->participants->first())->toBeInstanceOf(Participant::class);
    });

    it('generates unique invite codes', function () {
        $group1 = Group::factory()->create(['is_public' => true]);
        $group2 = Group::factory()->create(['is_public' => true]);
        
        expect($group1->invite_code)->not->toBe($group2->invite_code);
        expect($group1->invite_code)->toHaveLength(8);
    });

    it('scopes public groups correctly', function () {
        Group::factory()->create(['is_public' => true]);
        Group::factory()->create(['is_public' => false]);
        
        $publicGroups = Group::public()->get();
        
        expect($publicGroups)->toHaveCount(1);
        expect($publicGroups->first()->is_public)->toBeTrue();
    });

    it('calculates next turn participant correctly', function () {
        $participants = Participant::factory(3)->create(['group_id' => $this->group->id]);
        
        $nextParticipant = $this->group->getNextParticipant('round_robin');
        
        expect($nextParticipant)->toBeInstanceOf(Participant::class);
        expect($participants->contains($nextParticipant))->toBeTrue();
    });
});
```

### Action Testing

```php
<?php

use App\Actions\Group\CreateGroupAction;
use App\Events\GroupCreated;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Event;

describe('CreateGroupAction', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->action = new CreateGroupAction();
    });

    it('creates a group with valid data', function () {
        Event::fake();
        
        $data = [
            'name' => 'Test Group',
            'description' => 'A test group for unit testing',
            'algorithm' => 'random',
            'participants' => [
                ['name' => 'Alice'],
                ['name' => 'Bob'],
                ['name' => 'Charlie'],
            ],
        ];

        $group = $this->action->execute($this->user, $data);

        expect($group)->toBeInstanceOf(Group::class);
        expect($group->name)->toBe('Test Group');
        expect($group->user_id)->toBe($this->user->id);
        expect($group->participants)->toHaveCount(3);
        expect($group->settings['algorithm'])->toBe('random');

        Event::assertDispatched(GroupCreated::class, function ($event) use ($group) {
            return $event->group->id === $group->id;
        });
    });

    it('assigns creator as admin role', function () {
        $data = [
            'name' => 'Test Group',
            'algorithm' => 'random',
            'participants' => [['name' => 'Alice'], ['name' => 'Bob']],
        ];

        $group = $this->action->execute($this->user, $data);

        expect($this->user->hasRole('admin', $group))->toBeTrue();
    });

    it('logs group creation activity', function () {
        $data = [
            'name' => 'Test Group',
            'algorithm' => 'random',
            'participants' => [['name' => 'Alice'], ['name' => 'Bob']],
        ];

        $group = $this->action->execute($this->user, $data);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Group::class,
            'subject_id' => $group->id,
            'causer_type' => User::class,
            'causer_id' => $this->user->id,
            'description' => 'Group created',
        ]);
    });

    it('throws exception with invalid algorithm', function () {
        $data = [
            'name' => 'Test Group',
            'algorithm' => 'invalid_algorithm',
            'participants' => [['name' => 'Alice'], ['name' => 'Bob']],
        ];

        expect(fn() => $this->action->execute($this->user, $data))
            ->toThrow(InvalidArgumentException::class);
    });
});
```

### Service Testing

```php
<?php

use App\Models\Group;
use App\Models\Participant;
use App\Models\Turn;
use App\Services\TurnAlgorithmService;

describe('TurnAlgorithmService', function () {
    beforeEach(function () {
        $this->service = new TurnAlgorithmService();
        $this->group = Group::factory()->create();
        $this->participants = Participant::factory(4)->create(['group_id' => $this->group->id]);
    });

    describe('Random Algorithm', function () {
        it('selects a participant randomly', function () {
            $selectedParticipants = collect();

            // Run multiple times to test randomness
            for ($i = 0; $i < 20; $i++) {
                $selected = $this->service->executeTurn($this->group, 'random');
                $selectedParticipants->push($selected->id);
            }

            // Should have some variety in selections
            expect($selectedParticipants->unique())->toHaveCount(greaterThan(1));
            expect($this->participants->contains('id', $selectedParticipants->first()))->toBeTrue();
        });
    });

    describe('Round Robin Algorithm', function () {
        it('selects participants in order', function () {
            $expectedOrder = $this->participants->pluck('id')->toArray();
            $actualOrder = [];

            foreach ($expectedOrder as $expectedId) {
                $selected = $this->service->executeTurn($this->group, 'round_robin');
                $actualOrder[] = $selected->id;
                
                // Record the turn for next iteration
                Turn::create([
                    'group_id' => $this->group->id,
                    'participant_id' => $selected->id,
                    'algorithm' => 'round_robin',
                    'executed_at' => now(),
                ]);
            }

            expect($actualOrder)->toBe($expectedOrder);
        });

        it('cycles back to first participant after reaching end', function () {
            // Execute turns for all participants
            foreach ($this->participants as $participant) {
                Turn::create([
                    'group_id' => $this->group->id,
                    'participant_id' => $participant->id,
                    'algorithm' => 'round_robin',
                    'executed_at' => now(),
                ]);
            }

            // Next turn should be the first participant again
            $selected = $this->service->executeTurn($this->group, 'round_robin');
            expect($selected->id)->toBe($this->participants->first()->id);
        });
    });

    describe('Weighted Algorithm', function () {
        it('respects participant weights', function () {
            // Set different weights
            $this->participants[0]->update(['weight' => 10]);
            $this->participants[1]->update(['weight' => 1]);
            $this->participants[2]->update(['weight' => 1]);
            $this->participants[3]->update(['weight' => 1]);

            $selections = collect();

            // Run many iterations to test weight distribution
            for ($i = 0; $i < 100; $i++) {
                $selected = $this->service->executeTurn($this->group, 'weighted');
                $selections->push($selected->id);
            }

            // Heavily weighted participant should be selected more often
            $heavyParticipantSelections = $selections->filter(fn($id) => $id === $this->participants[0]->id);
            expect($heavyParticipantSelections->count())->toBeGreaterThan(50);
        });
    });
});
```

## Feature Testing (Integration Tests)

### API Testing

```php
<?php

use App\Models\Group;
use App\Models\Participant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('Group API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user, ['*']);
    });

    describe('POST /api/v1/groups', function () {
        it('creates a group with valid data', function () {
            $data = [
                'name' => 'New Group',
                'description' => 'A new group for testing',
                'algorithm' => 'random',
                'participants' => [
                    ['name' => 'Alice'],
                    ['name' => 'Bob'],
                    ['name' => 'Charlie'],
                ],
            ];

            $response = $this->postJson('/api/v1/groups', $data);

            $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Group created successfully',
                    'data' => [
                        'name' => 'New Group',
                        'description' => 'A new group for testing',
                    ],
                ]);

            $this->assertDatabaseHas('groups', [
                'name' => 'New Group',
                'user_id' => $this->user->id,
            ]);

            $this->assertDatabaseCount('participants', 3);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/v1/groups', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'participants']);
        });

        it('validates minimum participants', function () {
            $data = [
                'name' => 'Invalid Group',
                'participants' => [['name' => 'Only One']],
            ];

            $response = $this->postJson('/api/v1/groups', $data);

            $response->assertStatus(422)
                ->assertJsonValidationError('participants');
        });

        it('validates maximum participants', function () {
            $participants = array_fill(0, 51, ['name' => 'Participant']);
            
            $data = [
                'name' => 'Too Big Group',
                'participants' => $participants,
            ];

            $response = $this->postJson('/api/v1/groups', $data);

            $response->assertStatus(422)
                ->assertJsonValidationError('participants');
        });
    });

    describe('GET /api/v1/groups', function () {
        it('returns user groups with pagination', function () {
            Group::factory(15)->create(['user_id' => $this->user->id]);
            Group::factory(5)->create(); // Other user's groups

            $response = $this->getJson('/api/v1/groups');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id', 'name', 'description', 'participant_count',
                            'permissions', 'created_at', 'updated_at'
                        ]
                    ],
                    'links' => ['first', 'last', 'prev', 'next'],
                    'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                ]);

            expect($response->json('meta.total'))->toBe(15);
        });

        it('filters groups by search query', function () {
            Group::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'Project Alpha',
            ]);
            Group::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'Project Beta',
            ]);
            Group::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'Meeting Group',
            ]);

            $response = $this->getJson('/api/v1/groups?search=Project');

            $response->assertStatus(200);
            expect($response->json('meta.total'))->toBe(2);
        });
    });

    describe('POST /api/v1/groups/{group}/turns', function () {
        it('executes a turn successfully', function () {
            $group = Group::factory()->create(['user_id' => $this->user->id]);
            Participant::factory(3)->create(['group_id' => $group->id]);

            $response = $this->postJson("/api/v1/groups/{$group->id}/turns", [
                'algorithm' => 'random',
            ]);

            $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id', 'participant', 'algorithm', 'executed_at'
                    ]
                ]);

            $this->assertDatabaseHas('turns', [
                'group_id' => $group->id,
                'algorithm' => 'random',
            ]);
        });

        it('requires group membership to execute turn', function () {
            $otherUser = User::factory()->create();
            $group = Group::factory()->create(['user_id' => $otherUser->id]);
            Participant::factory(3)->create(['group_id' => $group->id]);

            $response = $this->postJson("/api/v1/groups/{$group->id}/turns", [
                'algorithm' => 'random',
            ]);

            $response->assertStatus(403);
        });
    });
});
```

### Authentication Testing

```php
<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('Authentication', function () {
    describe('POST /api/v1/auth/register', function () {
        it('registers a new user', function () {
            $userData = [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ];

            $response = $this->postJson('/api/v1/auth/register', $userData);

            $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user' => ['id', 'name', 'email'],
                        'token',
                    ],
                ]);

            $this->assertDatabaseHas('users', [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ]);
        });

        it('validates password requirements', function () {
            $userData = [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => 'weak',
                'password_confirmation' => 'weak',
            ];

            $response = $this->postJson('/api/v1/auth/register', $userData);

            $response->assertStatus(422)
                ->assertJsonValidationError('password');
        });
    });

    describe('POST /api/v1/auth/login', function () {
        it('authenticates user with valid credentials', function () {
            $user = User::factory()->create([
                'email' => 'john@example.com',
                'password' => bcrypt('Password123!'),
            ]);

            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'john@example.com',
                'password' => 'Password123!',
            ]);

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user' => ['id', 'name', 'email'],
                        'token',
                    ],
                ]);
        });

        it('rejects invalid credentials', function () {
            $user = User::factory()->create([
                'email' => 'john@example.com',
                'password' => bcrypt('Password123!'),
            ]);

            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'john@example.com',
                'password' => 'WrongPassword',
            ]);

            $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid credentials',
                ]);
        });
    });

    describe('POST /api/v1/auth/logout', function () {
        it('revokes authentication token', function () {
            $user = User::factory()->create();
            Sanctum::actingAs($user, ['*']);

            $response = $this->postJson('/api/v1/auth/logout');

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Logged out successfully',
                ]);

            $this->assertDatabaseMissing('personal_access_tokens', [
                'tokenable_id' => $user->id,
                'tokenable_type' => User::class,
            ]);
        });
    });
});
```

## Browser Testing (E2E)

### Dusk Setup and Configuration

```php
<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class GroupManagementTest extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset database for each test
        $this->artisan('migrate:fresh');
    }

    /** @test */
    public function user_can_create_and_manage_group()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                ->type('email', $user->email)
                ->type('password', 'password')
                ->press('Log In')
                ->assertPathIs('/dashboard')
                
                // Navigate to create group
                ->clickLink('Create Group')
                ->assertPathIs('/groups/create')
                
                // Fill out group form
                ->type('name', 'Test Group')
                ->type('description', 'A test group for browser testing')
                ->select('algorithm', 'random')
                
                // Add participants
                ->type('participants[0][name]', 'Alice')
                ->press('Add Participant')
                ->type('participants[1][name]', 'Bob')
                ->press('Add Participant')
                ->type('participants[2][name]', 'Charlie')
                
                // Submit form
                ->press('Create Group')
                ->assertPathIs('/groups/*')
                ->assertSee('Test Group')
                ->assertSee('Alice')
                ->assertSee('Bob')
                ->assertSee('Charlie')
                
                // Execute a turn
                ->press('Execute Turn')
                ->waitForText('Turn executed!')
                ->assertSeeIn('.turn-result', 'Selected:')
                
                // View turn history
                ->clickLink('Turn History')
                ->assertSee('Turn History')
                ->assertElementCount('.turn-record', 1)
                
                // Edit group
                ->clickLink('Edit Group')
                ->clear('name')
                ->type('name', 'Updated Test Group')
                ->press('Update Group')
                ->assertSee('Updated Test Group');
        });
    }

    /** @test */
    public function group_turn_execution_works_with_different_algorithms()
    {
        $user = User::factory()->create();
        
        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/groups/create')
                ->type('name', 'Algorithm Test Group')
                ->select('algorithm', 'round_robin')
                ->type('participants[0][name]', 'Alice')
                ->press('Add Participant')
                ->type('participants[1][name]', 'Bob')
                ->press('Add Participant')
                ->type('participants[2][name]', 'Charlie')
                ->press('Create Group')
                
                // Execute multiple turns to test round-robin
                ->press('Execute Turn')
                ->waitForText('Turn executed!')
                ->storeText('.selected-participant', 'first')
                
                ->press('Execute Turn')
                ->waitForText('Turn executed!')
                ->storeText('.selected-participant', 'second')
                
                ->press('Execute Turn')
                ->waitForText('Turn executed!')
                ->storeText('.selected-participant', 'third')
                
                // Verify round-robin order
                ->with('.turn-history', function ($history) {
                    $history->assertSeeIn('.turn-record:nth-child(1)', 'Alice')
                        ->assertSeeIn('.turn-record:nth-child(2)', 'Bob')
                        ->assertSeeIn('.turn-record:nth-child(3)', 'Charlie');
                });
        });
    }

    /** @test */
    public function responsive_design_works_on_mobile()
    {
        $user = User::factory()->create();
        
        $this->browse(function (Browser $browser) use ($user) {
            $browser->resize(375, 667) // iPhone SE dimensions
                ->loginAs($user)
                ->visit('/dashboard')
                ->assertVisible('.mobile-menu-button')
                ->click('.mobile-menu-button')
                ->assertVisible('.mobile-navigation')
                ->clickLink('Create Group')
                ->assertPathIs('/groups/create')
                
                // Test mobile form layout
                ->assertVisible('input[name="name"]')
                ->assertVisible('select[name="algorithm"]')
                ->type('name', 'Mobile Test Group')
                ->select('algorithm', 'random')
                ->type('participants[0][name]', 'Mobile Alice')
                ->press('Add Participant')
                ->type('participants[1][name]', 'Mobile Bob')
                ->press('Create Group')
                ->assertSee('Mobile Test Group')
                
                // Test mobile turn execution
                ->press('Execute Turn')
                ->waitForText('Turn executed!')
                ->assertVisible('.turn-result');
        });
    }
}
```

## Test Data Management

### Factories

```php
<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->paragraph(),
            'user_id' => User::factory(),
            'settings' => [
                'algorithm' => $this->faker->randomElement(['random', 'round_robin', 'weighted']),
                'allow_duplicates' => $this->faker->boolean(),
            ],
            'is_public' => $this->faker->boolean(30), // 30% chance of being public
            'invite_code' => $this->faker->optional()->regexify('[A-Z0-9]{8}'),
        ];
    }

    public function public(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_public' => true,
            'invite_code' => $this->faker->regexify('[A-Z0-9]{8}'),
        ]);
    }

    public function private(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_public' => false,
            'invite_code' => null,
        ]);
    }

    public function withParticipants(int $count = 3): static
    {
        return $this->afterCreating(function (Group $group) use ($count) {
            Participant::factory($count)->create(['group_id' => $group->id]);
        });
    }
}
```

### Seeders for Testing

```php
<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Participant;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestingSeeder extends Seeder
{
    public function run(): void
    {
        // Create test users
        $admin = User::factory()->create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
        ]);

        // Create test groups
        $group1 = Group::factory()->create([
            'name' => 'Daily Standup',
            'description' => 'Who starts the daily standup?',
            'user_id' => $admin->id,
            'settings' => ['algorithm' => 'round_robin'],
        ]);

        $group2 = Group::factory()->create([
            'name' => 'Coffee Run',
            'description' => 'Who gets coffee for the team?',
            'user_id' => $user->id,
            'settings' => ['algorithm' => 'random'],
        ]);

        // Create participants
        $participants1 = Participant::factory(4)->create(['group_id' => $group1->id]);
        $participants2 = Participant::factory(6)->create(['group_id' => $group2->id]);

        // Create some turn history
        for ($i = 0; $i < 10; $i++) {
            Turn::factory()->create([
                'group_id' => $group1->id,
                'participant_id' => $participants1->random()->id,
                'executed_at' => now()->subDays(rand(1, 30)),
            ]);
        }

        for ($i = 0; $i < 15; $i++) {
            Turn::factory()->create([
                'group_id' => $group2->id,
                'participant_id' => $participants2->random()->id,
                'executed_at' => now()->subDays(rand(1, 45)),
            ]);
        }
    }
}
```

## Performance Testing

### Database Performance Tests

```php
<?php

use App\Models\Group;
use App\Models\Turn;
use Illuminate\Support\Facades\DB;

describe('Database Performance', function () {
    it('handles large datasets efficiently', function () {
        // Create a group with many participants and turns
        $group = Group::factory()->create();
        Participant::factory(100)->create(['group_id' => $group->id]);
        
        // Measure query performance
        $startTime = microtime(true);
        
        DB::enableQueryLog();
        
        $result = $group->load(['participants', 'turns' => function ($query) {
            $query->with('participant')->latest()->take(50);
        }]);
        
        $queryLog = DB::getQueryLog();
        $endTime = microtime(true);
        
        // Assertions
        expect($endTime - $startTime)->toBeLessThan(0.1); // Should complete in < 100ms
        expect(count($queryLog))->toBeLessThan(5); // Should not generate N+1 queries
    });

    it('optimizes turn algorithm performance', function () {
        $group = Group::factory()->create();
        Participant::factory(1000)->create(['group_id' => $group->id]);
        Turn::factory(5000)->create(['group_id' => $group->id]);
        
        $service = new TurnAlgorithmService();
        
        $startTime = microtime(true);
        $result = $service->executeTurn($group, 'weighted');
        $endTime = microtime(true);
        
        expect($endTime - $startTime)->toBeLessThan(0.05); // Should complete in < 50ms
        expect($result)->toBeInstanceOf(Participant::class);
    });
});
```

## Test Automation & CI/CD

### GitHub Actions Configuration

```yaml
# .github/workflows/tests.yml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: turns_test
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
      
      redis:
        image: redis:7-alpine
        options: --health-cmd="redis-cli ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
    - uses: actions/checkout@v4
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.3'
        extensions: mbstring, dom, fileinfo, mysql, redis
        coverage: xdebug
    
    - name: Cache Composer dependencies
      uses: actions/cache@v3
      with:
        path: /tmp/composer-cache
        key: ${{ runner.os }}-${{ hashFiles('**/composer.lock') }}
    
    - name: Install Composer dependencies
      run: composer install --no-progress --prefer-dist --optimize-autoloader
    
    - name: Copy environment file
      run: cp .env.testing .env
    
    - name: Generate application key
      run: php artisan key:generate
    
    - name: Run database migrations
      run: php artisan migrate --force
    
    - name: Run tests with coverage
      run: |
        vendor/bin/pest --coverage --min=95 --coverage-clover=coverage.xml
    
    - name: Upload coverage to Codecov
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage.xml
    
    - name: Run Browser Tests
      run: |
        php artisan dusk:chrome-driver
        php artisan serve &
        vendor/bin/pest --group=browser
```

### Pre-commit Hooks

```bash
#!/bin/sh
# .git/hooks/pre-commit

# Run tests before commit
echo "Running tests..."
vendor/bin/pest --stop-on-failure

if [ $? -ne 0 ]; then
    echo "Tests failed. Commit aborted."
    exit 1
fi

# Run code style checks
echo "Checking code style..."
vendor/bin/php-cs-fixer fix --dry-run --diff

if [ $? -ne 0 ]; then
    echo "Code style issues found. Please run 'composer fix-style' and try again."
    exit 1
fi

echo "All checks passed. Proceeding with commit."
```

## Test Coverage & Reporting

### Coverage Configuration

```php
// pest.php
<?php

uses(Tests\TestCase::class)->in('Feature');
uses(Tests\TestCase::class)->in('Unit');

// Custom coverage requirements
coverage()->includeDirectory('app')
    ->excludeDirectory('app/Console')
    ->requiresCoverage(95.0)
    ->requiresCoverageForDirectory('app/Actions', 98.0)
    ->requiresCoverageForDirectory('app/Models', 95.0)
    ->requiresCoverageForDirectory('app/Services', 98.0);
```

### Coverage Reports

```bash
# Generate HTML coverage report
vendor/bin/pest --coverage-html reports/coverage

# Generate text coverage report
vendor/bin/pest --coverage-text

# Generate Clover XML for CI
vendor/bin/pest --coverage-clover coverage.xml

# Check minimum coverage requirements
vendor/bin/pest --coverage --min=95
```

This comprehensive testing strategy ensures high code quality, reliability, and maintainability for the Laravel backend application.
