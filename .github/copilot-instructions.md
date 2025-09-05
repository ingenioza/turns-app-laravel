# GitHub Copilot Instructions - Laravel Project

You are an expert Laravel/PHP developer working on the "Turns" app backend - a comprehensive API and web interface for group turn-taking and decision-making. This is a standalone Laravel project serving both mobile apps and web users.

## Project Context

**Organization**: inGenIO (ingenioza)  
**Repository**: https://github.com/ingenioza/turns-app-laravel  
**Project Type**: Laravel backend with Inertia.js React frontend

This Laravel application provides:
- **RESTful API**: Complete API for Flutter mobile/web app integration
- **Web Interface**: Full-featured web application using Inertia.js + React + TypeScript
- **Authentication System**: Multi-provider OAuth (Google, Apple) + email/password
- **Group Management**: Persistent groups with participant management and sharing
- **Turn Algorithm Engine**: Multiple algorithms (random, round-robin, weighted, custom)
- **Real-time Features**: Push notifications and live updates
- **Activity Tracking**: Comprehensive logging and analytics
- **Permission System**: Role-based access control

## Technology Stack & Architecture

### Backend Technologies
- **Framework**: Laravel 12 (PHP 8.4+)
- **Database**: MySQL 8.0+ / PostgreSQL 15+ (production)
- **Cache**: Redis for sessions, cache, and queues
- **Queue System**: Redis with Laravel Horizon for monitoring
- **Search**: Laravel Scout with Meilisearch/Algolia
- **Storage**: Local development, S3 for production
- **Mail**: Laravel Mail with Mailtrap (dev), SendGrid (prod)

### Frontend Technologies (Inertia.js Web Interface)
- **Framework**: Inertia.js + React 18 + TypeScript
- **Styling**: Tailwind CSS 3+ with HeadlessUI components
- **Build Tool**: Vite for fast builds and HMR
- **State Management**: React Context + Custom hooks
- **Forms**: Inertia.js forms with validation
- **Icons**: Heroicons and Lucide React

### Required Packages
- **Authentication**: Laravel Sanctum for API, Laravel Breeze for web
- **Permissions**: spatie/laravel-permission for role-based access
- **Activity Logging**: spatie/laravel-activitylog for audit trails
- **Settings**: spatie/laravel-settings for app configuration
- **Validation**: spatie/laravel-validation-rules for enhanced validation
- **Route Caching**: tightenco/ziggy for JavaScript route generation
- **Testing**: Pest PHP for testing framework

## Architecture Patterns

### Laravel Application Structure
```
app/
├── Actions/                          # Single-purpose business logic
│   ├── Group/                        # Group-related actions
│   ├── Turn/                         # Turn algorithm actions
│   └── Participant/                  # Participant management actions
├── Events/                           # Domain events
├── Listeners/                        # Event listeners
├── Http/
│   ├── Controllers/
│   │   ├── Api/                      # API controllers for mobile app
│   │   └── Web/                      # Web controllers for Inertia
│   ├── Middleware/                   # Custom middleware
│   ├── Requests/                     # Form request validation
│   └── Resources/                    # API resources and collections
├── Models/                           # Eloquent models
├── Policies/                         # Authorization policies
├── Repositories/                     # Data access layer
├── Services/                         # External service integrations
└── Jobs/                            # Queued jobs

resources/
├── js/
│   ├── Components/                   # Reusable React components
│   ├── Pages/                        # Inertia.js page components
│   ├── Layouts/                      # Page layouts
│   ├── Hooks/                        # Custom React hooks
│   └── Types/                        # TypeScript type definitions
└── css/                             # Tailwind CSS and custom styles
```

### Domain-Driven Structure
```
Group Management:
├── Models: Group, Participant, GroupInvite
├── Actions: CreateGroup, AddParticipant, ShareGroup
├── Policies: GroupPolicy, ParticipantPolicy
├── Events: GroupCreated, ParticipantJoined
└── Jobs: SendGroupInvitation, NotifyGroupUpdate

Turn System:
├── Models: Turn, TurnHistory, TurnAlgorithm
├── Actions: ExecuteTurn, RecordTurnHistory
├── Services: TurnAlgorithmService
└── Events: TurnExecuted, TurnCompleted

User Management:
├── Models: User, Role, Permission
├── Actions: RegisterUser, AssignRole
├── Policies: UserPolicy
└── Events: UserRegistered, RoleAssigned
```

## Coding Standards & Best Practices

### PHP Standards
- Follow **PSR-12** coding standards strictly
- Use **PHP 8.4** features (typed properties, match expressions, readonly properties)
- Implement **strict typing** everywhere: `declare(strict_types=1);`
- Use **meaningful variable and method names** (no abbreviations)
- Follow **Laravel naming conventions** for all components

### Laravel Specific Patterns
```php
<?php

declare(strict_types=1);

namespace App\Actions\Group;

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

final class CreateGroupAction
{
    public function execute(User $user, array $data): Group
    {
        return DB::transaction(function () use ($user, $data) {
            $group = Group::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'user_id' => $user->id,
                'settings' => [
                    'algorithm' => $data['algorithm'] ?? 'random',
                    'allow_duplicates' => $data['allow_duplicates'] ?? false,
                ],
            ]);

            $user->assignRole('group-admin', $group);

            activity()
                ->performedOn($group)
                ->causedBy($user)
                ->log('Group created');

            return $group->fresh();
        });
    }
}

// Model with proper relationships and attributes
class Group extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, HasRoles;

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'settings',
        'is_public',
        'invite_code',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_public' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static $logAttributes = ['*'];
    protected static $logOnlyDirty = true;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function turns(): HasMany
    {
        return $this->hasMany(Turn::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
```

### API Design Patterns
```php
// API Resource with proper transformation
class GroupResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'participant_count' => $this->participants_count,
            'last_turn_at' => $this->last_turn_at?->toISOString(),
            'settings' => [
                'algorithm' => $this->settings['algorithm'],
                'allow_duplicates' => $this->settings['allow_duplicates'],
            ],
            'permissions' => [
                'can_edit' => $request->user()?->can('update', $this->resource),
                'can_delete' => $request->user()?->can('delete', $this->resource),
            ],
            'links' => [
                'self' => route('api.groups.show', $this->uuid),
                'participants' => route('api.groups.participants.index', $this->uuid),
                'turns' => route('api.groups.turns.index', $this->uuid),
            ],
        ];
    }
}

// Form Request with comprehensive validation
class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Group::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'min:2'],
            'description' => ['nullable', 'string', 'max:1000'],
            'algorithm' => ['required', 'in:random,round_robin,weighted,custom'],
            'allow_duplicates' => ['boolean'],
            'participants' => ['required', 'array', 'min:2', 'max:50'],
            'participants.*.name' => ['required', 'string', 'max:100'],
            'participants.*.weight' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'participants.min' => 'A group must have at least 2 participants.',
            'participants.max' => 'A group cannot have more than 50 participants.',
        ];
    }
}
```

## Project-Specific Requirements

### Turn Algorithm Implementation
```php
namespace App\Services;

abstract class TurnAlgorithm
{
    abstract public function selectNext(
        Collection $participants,
        ?Collection $history = null
    ): Participant;

    abstract public function getName(): string;
    abstract public function getDescription(): string;
}

class RandomTurnAlgorithm extends TurnAlgorithm
{
    public function selectNext(
        Collection $participants,
        ?Collection $history = null
    ): Participant {
        return $participants->random();
    }

    public function getName(): string
    {
        return 'Random';
    }

    public function getDescription(): string
    {
        return 'Selects a random participant with equal probability for all.';
    }
}

class RoundRobinTurnAlgorithm extends TurnAlgorithm
{
    public function selectNext(
        Collection $participants,
        ?Collection $history = null
    ): Participant {
        if (!$history || $history->isEmpty()) {
            return $participants->first();
        }

        $lastTurn = $history->sortByDesc('created_at')->first();
        $lastParticipantIndex = $participants->search(fn($p) => $p->id === $lastTurn->participant_id);
        
        $nextIndex = ($lastParticipantIndex + 1) % $participants->count();
        return $participants->values()[$nextIndex];
    }

    public function getName(): string
    {
        return 'Round Robin';
    }

    public function getDescription(): string
    {
        return 'Cycles through participants in order, ensuring fair rotation.';
    }
}
```

### Authentication & Authorization System
```php
// Multi-provider authentication setup
class AuthenticationService
{
    public function attemptLogin(string $email, string $password): ?User
    {
        if (Auth::attempt(['email' => $email, 'password' => $password])) {
            $user = Auth::user();
            activity()
                ->causedBy($user)
                ->log('User logged in');
            return $user;
        }
        return null;
    }

    public function loginWithProvider(string $provider, array $userData): User
    {
        $user = User::firstOrCreate(
            ['email' => $userData['email']],
            [
                'name' => $userData['name'],
                'provider' => $provider,
                'provider_id' => $userData['id'],
                'email_verified_at' => now(),
            ]
        );

        Auth::login($user);
        
        activity()
            ->causedBy($user)
            ->withProperties(['provider' => $provider])
            ->log('User logged in via OAuth');

        return $user;
    }
}

// Role and permission setup
class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view groups
    }

    public function view(User $user, Group $group): bool
    {
        return $group->is_public || 
               $user->id === $group->user_id ||
               $user->hasRole('member', $group);
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function update(User $user, Group $group): bool
    {
        return $user->hasRole(['admin', 'moderator'], $group);
    }

    public function delete(User $user, Group $group): bool
    {
        return $user->id === $group->user_id ||
               $user->hasRole('admin', $group);
    }
}
```

## Frontend (React + Inertia.js) Patterns

### TypeScript Interfaces
```typescript
// Type definitions for Laravel resources
interface Group {
  id: string;
  name: string;
  description?: string;
  participant_count: number;
  last_turn_at?: string;
  settings: {
    algorithm: 'random' | 'round_robin' | 'weighted' | 'custom';
    allow_duplicates: boolean;
  };
  permissions: {
    can_edit: boolean;
    can_delete: boolean;
  };
  links: {
    self: string;
    participants: string;
    turns: string;
  };
}

interface Participant {
  id: string;
  name: string;
  weight?: number;
  avatar?: string;
  turn_count: number;
  last_turn_at?: string;
}

// Inertia.js page props
interface PageProps {
  auth: {
    user: User;
  };
  flash: {
    success?: string;
    error?: string;
  };
  errors: Record<string, string>;
}
```

### React Component Patterns
```typescript
// Inertia.js page component
interface GroupsIndexProps extends PageProps {
  groups: {
    data: Group[];
    links: PaginationLinks;
    meta: PaginationMeta;
  };
  filters: {
    search?: string;
    algorithm?: string;
  };
}

export default function GroupsIndex({ groups, filters }: GroupsIndexProps) {
  const { data, setData, get, processing } = useForm({
    search: filters.search || '',
    algorithm: filters.algorithm || '',
  });

  const handleSearch = (e: FormEvent) => {
    e.preventDefault();
    get(route('groups.index'), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  return (
    <AuthenticatedLayout title="Groups">
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <h1 className="text-2xl font-bold text-gray-900">My Groups</h1>
          <Link
            href={route('groups.create')}
            className="btn-primary"
          >
            Create Group
          </Link>
        </div>

        <GroupsFilter
          data={data}
          setData={setData}
          onSubmit={handleSearch}
          processing={processing}
        />

        <GroupsList groups={groups.data} />

        <Pagination links={groups.links} meta={groups.meta} />
      </div>
    </AuthenticatedLayout>
  );
}
```

## Database Design & Migrations

### Migration Best Practices
```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->json('settings')->default('{}');
            $table->boolean('is_public')->default(false);
            $table->string('invite_code', 10)->unique()->nullable();
            $table->timestamp('last_turn_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index(['is_public', 'created_at']);
            $table->index('invite_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
```

## Testing Strategy

### Feature Testing with Pest
```php
it('allows authenticated users to create groups', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)
        ->post(route('groups.store'), [
            'name' => 'Test Group',
            'description' => 'A test group',
            'algorithm' => 'random',
            'participants' => [
                ['name' => 'Alice'],
                ['name' => 'Bob'],
            ],
        ]);

    $response->assertRedirect();
    
    $this->assertDatabaseHas('groups', [
        'name' => 'Test Group',
        'user_id' => $user->id,
    ]);

    expect(Group::where('name', 'Test Group')->first())
        ->participants
        ->toHaveCount(2);
});

it('prevents unauthorized access to private groups', function () {
    $owner = User::factory()->create();
    $unauthorized = User::factory()->create();
    
    $group = Group::factory()
        ->for($owner)
        ->private()
        ->create();

    $response = $this->actingAs($unauthorized)
        ->get(route('groups.show', $group));

    $response->assertForbidden();
});

it('executes turn algorithms correctly', function () {
    $user = User::factory()->create();
    $group = Group::factory()
        ->for($user)
        ->withParticipants(3)
        ->create(['settings' => ['algorithm' => 'round_robin']]);

    $response = $this->actingAs($user)
        ->post(route('groups.turns.store', $group));

    $response->assertOk();
    
    $turn = $group->turns()->latest()->first();
    expect($turn)->not->toBeNull();
    expect($turn->participant)->toBeInstanceOf(Participant::class);
});
```

## Performance & Optimization

### Database Optimization
```php
// Eager loading to prevent N+1 queries
class GroupController extends Controller
{
    public function index(): Response
    {
        $groups = Group::query()
            ->with(['participants', 'user'])
            ->withCount('participants')
            ->latest('last_turn_at')
            ->paginate(15);

        return Inertia::render('Groups/Index', [
            'groups' => GroupResource::collection($groups),
        ]);
    }
}

// Query optimization with indexes
Group::query()
    ->where('is_public', true)
    ->where('created_at', '>', now()->subDays(30))
    ->orderBy('last_turn_at', 'desc')
    ->limit(50)
    ->get();
```

### Caching Strategy
```php
class GroupService
{
    public function getPopularGroups(): Collection
    {
        return Cache::remember('popular_groups', 3600, function () {
            return Group::query()
                ->where('is_public', true)
                ->withCount('participants')
                ->having('participants_count', '>=', 5)
                ->orderBy('participants_count', 'desc')
                ->limit(20)
                ->get();
        });
    }

    public function clearGroupCache(Group $group): void
    {
        Cache::forget("group_{$group->id}");
        Cache::forget('popular_groups');
    }
}
```

## Security Implementation

### API Security
```php
// Rate limiting
Route::middleware(['auth:sanctum', 'throttle:api'])
    ->prefix('api/v1')
    ->group(function () {
        Route::apiResource('groups', GroupController::class);
        Route::post('groups/{group}/turns', [TurnController::class, 'store'])
            ->middleware('throttle:turns');
    });

// Custom middleware for group access
class EnsureGroupAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $group = $request->route('group');
        
        if (!$request->user()->can('view', $group)) {
            abort(403, 'Access denied to this group.');
        }

        return $next($request);
    }
}
```

### Input Validation & Sanitization
```php
class TurnRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'algorithm_override' => ['nullable', 'in:random,round_robin,weighted'],
            'exclude_participants' => ['nullable', 'array'],
            'exclude_participants.*' => ['exists:participants,id'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);
        
        // Additional validation logic
        if (isset($validated['exclude_participants'])) {
            $group = $this->route('group');
            $availableParticipants = $group->participants->count() - 
                count($validated['exclude_participants']);
                
            if ($availableParticipants < 1) {
                throw ValidationException::withMessages([
                    'exclude_participants' => 'Cannot exclude all participants.',
                ]);
            }
        }

        return $validated;
    }
}
```

## Code Generation Guidelines

When generating code, always:

1. **Follow Clean Architecture** with proper separation of concerns
2. **Use Actions** for complex business logic operations
3. **Implement proper validation** in Form Requests
4. **Add comprehensive error handling** with custom exceptions
5. **Include authorization checks** using Policies
6. **Write corresponding tests** using Pest PHP
7. **Add activity logging** for audit trails
8. **Use proper database relationships** and constraints
9. **Implement caching** for performance optimization
10. **Follow security best practices** for all endpoints

## Quality Checklist

Before suggesting code, ensure:
- [ ] Follows Laravel conventions and PSR-12 standards
- [ ] Uses strict typing and PHP 8.4 features
- [ ] Implements proper validation and authorization
- [ ] Includes comprehensive error handling
- [ ] Has corresponding Pest tests
- [ ] Uses Spatie packages correctly
- [ ] Follows security best practices
- [ ] Implements proper database relationships
- [ ] Includes activity logging where appropriate
- [ ] Optimizes for performance with proper indexing
- [ ] Uses proper TypeScript types for frontend
- [ ] Follows React best practices for Inertia components

## Constraints & Requirements

- **PHP Version**: 8.4+
- **Laravel Version**: 12.x
- **Database**: MySQL 8.0+ or PostgreSQL 15+
- **Node.js**: 18+ for frontend build
- **Testing**: Pest PHP with 90%+ coverage
- **Performance**: API responses under 200ms
- **Security**: OWASP compliance required
- **Accessibility**: WCAG 2.1 AA for web interface

---

**Organization**: inGenIO - Building innovative productivity tools

## Technology Stack

### Backend
- **Framework**: Laravel 12 (PHP 8.4+)
- **Database**: MySQL/PostgreSQL
- **Queue**: Redis
- **Storage**: Local/S3
- **Testing**: PHPUnit

### Frontend
- **Framework**: Inertia.js + React 18 + TypeScript
- **Styling**: Tailwind CSS
- **Build Tool**: Vite

### Required Packages
- **Permissions**: spatie/laravel-permission
- **Activity Logging**: spatie/laravel-activitylog
- **API Resources**: Laravel Sanctum
- **Frontend Integration**: inertiajs/inertia-laravel, tightenco/ziggy

## Architecture Patterns

### Follow Laravel Best Practices
- **Repository Pattern**: For data access abstraction
- **Action Pattern**: For complex business logic operations
- **Service Pattern**: For external integrations
- **Event/Listener Pattern**: For decoupled features
- **Policy Pattern**: For authorization logic

### Project Structure
```
app/
├── Actions/        # Business logic actions
├── Events/         # Domain events
├── Http/
│   ├── Controllers/    # API and web controllers
│   ├── Requests/       # Form request validation
│   └── Resources/      # API resources
├── Models/         # Eloquent models
├── Policies/       # Authorization policies
├── Repositories/   # Data access layer
└── Services/       # External service integrations

resources/
├── js/
│   ├── Components/     # React components
│   ├── Pages/          # Inertia.js pages
│   └── Types/          # TypeScript definitions
└── css/           # Tailwind CSS
```

## Coding Standards

### PHP Standards
- Follow **PSR-12** coding standards
- Use **PHP 8.4** features (typed properties, match expressions, etc.)
- Implement **strict typing** where possible
- Use **meaningful variable and method names**
- Follow **Laravel naming conventions**

### Database Design
- Use **proper foreign key constraints**
- Implement **soft deletes** where appropriate
- Create **database indexes** for performance
- Use **migration files** for all schema changes
- Follow **Laravel migration conventions**

### API Design
- Follow **RESTful API principles**
- Use **proper HTTP status codes**
- Implement **consistent error responses**
- Use **API versioning** when necessary
- Include **comprehensive API documentation**

## Required Patterns

### Models & Relationships
```php
class Group extends Model
{
    use HasFactory, SoftDeletes;
    use LogsActivity, HasRoles; // Spatie packages
    
    protected $fillable = [...];
    protected $casts = [...];
    
    // Always include proper relationships
    public function participants(): HasMany { }
    public function turns(): HasMany { }
}
```

### Actions for Business Logic
```php
class CreateTurnAction
{
    public function execute(Group $group, array $data): Turn
    {
        // Complex business logic here
        // Include proper validation and error handling
    }
}
```

### API Resources
```php
class GroupResource extends JsonResource
{
    public function toArray($request): array
    {
        // Consistent API response format
    }
}
```

### Form Requests
```php
class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool { }
    public function rules(): array { }
}
```

## Spatie Package Integration

### Permissions (spatie/laravel-permission)
- Use **roles and permissions** for authorization
- Implement **policies** for complex authorization logic
- Use **middleware** for route protection
- Create **seeders** for default roles/permissions

### Activity Log (spatie/laravel-activitylog)
- Log **all significant user actions**
- Include **proper activity descriptions**
- Log **model changes** automatically
- Use for **audit trails** and **notifications**

```php
// In models
use LogsActivity;

protected static $logAttributes = ['*'];
protected static $logOnlyDirty = true;

// Manual logging
activity()
    ->performedOn($group)
    ->causedBy($user)
    ->log('User joined group');
```

## Frontend (React + Inertia.js)

### TypeScript Requirements
- Use **strict TypeScript** configuration
- Define **proper interfaces** for all data
- Use **type-safe** API calls
- Implement **proper error boundaries**

### React Best Practices
- Use **functional components** with hooks
- Implement **proper state management**
- Use **React.memo** for performance optimization
- Follow **React accessibility** guidelines

### Inertia.js Patterns
```typescript
// Page components
interface Props {
    groups: Group[];
    user: User;
}

export default function GroupsIndex({ groups, user }: Props) {
    // Component implementation
}

// Forms with Inertia
const { data, setData, post, processing, errors } = useForm({
    name: '',
    description: '',
});
```

## Testing Requirements

### Backend Testing
- Write **feature tests** for API endpoints
- Write **unit tests** for actions and services
- Use **factories** for test data
- Test **authorization** and **validation**
- Achieve **80%+ test coverage**

### Frontend Testing
- Write **component tests** for React components
- Test **user interactions** and **form submissions**
- Mock **API calls** appropriately
- Test **error states** and **loading states**

## Security Requirements

### API Security
- Use **Laravel Sanctum** for API authentication
- Implement **rate limiting** on API routes
- Validate **all user inputs**
- Use **HTTPS** in production
- Implement **CORS** properly

### Authorization
- Use **policies** for complex authorization
- Implement **middleware** for route protection
- Follow **principle of least privilege**
- Log **security-related events**

## Performance Requirements

### Database Optimization
- Use **eager loading** to prevent N+1 queries
- Implement **database indexing**
- Use **query optimization**
- Implement **caching** where appropriate

### Frontend Optimization
- Implement **code splitting**
- Use **lazy loading** for components
- Optimize **bundle size**
- Implement **proper caching strategies**

## Feature-Specific Requirements

### Turn Algorithms
- Implement as **strategy pattern**
- Support **configurable algorithms**
- Track **fairness metrics**
- Handle **edge cases** properly

### Notifications
- Use **Laravel queues** for background processing
- Implement **multiple notification channels**
- Support **push notifications**
- Track **notification delivery**

### Group Management
- Support **group sharing** via links/codes
- Implement **participant management**
- Track **group history**
- Support **group settings**

## Code Generation Guidelines

When generating code:

1. **Include proper imports and namespaces**
2. **Add comprehensive PHPDoc comments**
3. **Implement proper error handling**
4. **Include validation logic**
5. **Write corresponding tests**
6. **Follow established patterns**
7. **Include proper authorization**
8. **Log activities appropriately**

## Quality Checklist
Before suggesting code, ensure:
- [ ] Follows Laravel conventions
- [ ] Includes proper validation
- [ ] Has authorization checks
- [ ] Includes error handling
- [ ] Has corresponding tests
- [ ] Uses Spatie packages correctly
- [ ] Follows security best practices
- [ ] Is performant and scalable
- [ ] Includes proper documentation
- [ ] Handles edge cases

## Constraints
- Never use deprecated Laravel features
- Always implement proper authorization
- Don't expose sensitive data in API responses
- Maintain backwards compatibility for API changes
- Follow database design best practices
- Prioritize security and performance
