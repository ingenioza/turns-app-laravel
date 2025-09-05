# Laravel Backend Architecture

## Architecture Overview

This Laravel application follows **Domain-Driven Design** principles with a layered architecture that promotes clean separation of concerns, testability, and maintainability.

### Architecture Layers

```
┌─────────────────────────────────────┐
│           Presentation Layer        │ ← Controllers, Resources, Requests
├─────────────────────────────────────┤
│           Application Layer         │ ← Actions, Services, Jobs
├─────────────────────────────────────┤
│             Domain Layer            │ ← Models, Policies, Events
├─────────────────────────────────────┤
│         Infrastructure Layer        │ ← Repositories, External Services
└─────────────────────────────────────┘
```

### Project Structure

```
app/
├── Actions/                          # Single-purpose business logic
│   ├── Group/                        # Group-related actions
│   │   ├── CreateGroupAction.php     # Create new group
│   │   ├── UpdateGroupAction.php     # Update group details
│   │   └── DeleteGroupAction.php     # Delete group
│   ├── Turn/                         # Turn algorithm actions
│   │   ├── ExecuteTurnAction.php     # Execute turn selection
│   │   └── RecordTurnAction.php      # Record turn history
│   └── Participant/                  # Participant management actions
├── Events/                           # Domain events
│   ├── GroupCreated.php              # Group creation event
│   ├── TurnExecuted.php              # Turn execution event
│   └── ParticipantJoined.php         # Participant join event
├── Listeners/                        # Event listeners
│   ├── SendGroupNotification.php     # Send notifications
│   └── LogGroupActivity.php          # Log activities
├── Http/
│   ├── Controllers/
│   │   ├── Api/                      # API controllers for mobile app
│   │   │   ├── GroupController.php   # Group API endpoints
│   │   │   ├── TurnController.php    # Turn API endpoints
│   │   │   └── AuthController.php    # Authentication endpoints
│   │   └── Web/                      # Web controllers for Inertia
│   │       ├── GroupController.php   # Group web interface
│   │       └── DashboardController.php # Dashboard interface
│   ├── Middleware/                   # Custom middleware
│   │   ├── EnsureGroupAccess.php     # Group access control
│   │   └── LogApiRequests.php        # API request logging
│   ├── Requests/                     # Form request validation
│   │   ├── StoreGroupRequest.php     # Group creation validation
│   │   ├── UpdateGroupRequest.php    # Group update validation
│   │   └── ExecuteTurnRequest.php    # Turn execution validation
│   └── Resources/                    # API resources and collections
│       ├── GroupResource.php         # Group API response
│       ├── TurnResource.php          # Turn API response
│       └── ParticipantResource.php   # Participant API response
├── Models/                           # Eloquent models
│   ├── Group.php                     # Group model with relationships
│   ├── Participant.php               # Participant model
│   ├── Turn.php                      # Turn history model
│   └── User.php                      # User model with roles
├── Policies/                         # Authorization policies
│   ├── GroupPolicy.php               # Group access authorization
│   ├── TurnPolicy.php                # Turn execution authorization
│   └── ParticipantPolicy.php         # Participant management authorization
├── Repositories/                     # Data access layer
│   ├── GroupRepository.php           # Group data operations
│   ├── TurnRepository.php            # Turn data operations
│   └── UserRepository.php            # User data operations
├── Services/                         # External service integrations
│   ├── TurnAlgorithmService.php      # Turn algorithm implementations
│   ├── NotificationService.php       # Push notification service
│   └── CalendarService.php           # Calendar integration service
└── Jobs/                            # Queued jobs
    ├── SendPushNotification.php      # Send push notifications
    ├── ProcessGroupInvitation.php    # Process group invitations
    └── GenerateAnalyticsReport.php   # Generate analytics

database/
├── migrations/                       # Database schema migrations
├── seeders/                          # Database seeders
├── factories/                        # Model factories for testing
└── schema/                           # Database schema documentation

resources/
├── js/
│   ├── Components/                   # Reusable React components
│   │   ├── Groups/                   # Group-related components
│   │   ├── Turns/                    # Turn-related components
│   │   └── Common/                   # Shared components
│   ├── Pages/                        # Inertia.js page components
│   │   ├── Groups/                   # Group management pages
│   │   ├── Dashboard/                # Dashboard pages
│   │   └── Auth/                     # Authentication pages
│   ├── Layouts/                      # Page layouts
│   │   ├── AppLayout.jsx             # Main application layout
│   │   └── AuthLayout.jsx            # Authentication layout
│   ├── Hooks/                        # Custom React hooks
│   │   ├── useGroups.js              # Group management hook
│   │   └── useNotifications.js       # Notification hook
│   └── Types/                        # TypeScript type definitions
│       ├── Group.ts                  # Group type definitions
│       ├── Turn.ts                   # Turn type definitions
│       └── User.ts                   # User type definitions
└── css/                             # Tailwind CSS and custom styles
```

## Design Patterns

### Action Pattern

Actions encapsulate single-purpose business operations:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Group;

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

            // Assign creator as admin
            $user->assignRole('admin', $group);

            // Log activity
            activity()
                ->performedOn($group)
                ->causedBy($user)
                ->log('Group created');

            // Dispatch event
            event(new GroupCreated($group, $user));

            return $group->fresh(['participants', 'user']);
        });
    }
}
```

### Repository Pattern

Repositories abstract data access logic:

```php
<?php

namespace App\Repositories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class GroupRepository
{
    public function findByUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Group::query()
            ->where('user_id', $user->id)
            ->orWhereHas('members', fn($query) => $query->where('user_id', $user->id))
            ->with(['participants', 'lastTurn'])
            ->withCount('participants')
            ->latest('updated_at')
            ->paginate($perPage);
    }

    public function findPublicGroups(int $perPage = 15): LengthAwarePaginator
    {
        return Group::query()
            ->where('is_public', true)
            ->with(['user', 'participants'])
            ->withCount(['participants', 'turns'])
            ->orderByDesc('participants_count')
            ->paginate($perPage);
    }

    public function findByInviteCode(string $code): ?Group
    {
        return Group::query()
            ->where('invite_code', $code)
            ->where('is_public', true)
            ->with(['participants', 'user'])
            ->first();
    }
}
```

### Service Pattern

Services handle complex business logic and external integrations:

```php
<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Participant;
use App\Models\Turn;
use Illuminate\Database\Eloquent\Collection;

class TurnAlgorithmService
{
    public function executeTurn(Group $group, string $algorithm = null): Participant
    {
        $algorithm = $algorithm ?? $group->settings['algorithm'] ?? 'random';
        $participants = $group->participants;
        $history = $group->turns()->with('participant')->latest()->take(50)->get();

        return match ($algorithm) {
            'random' => $this->executeRandomTurn($participants),
            'round_robin' => $this->executeRoundRobinTurn($participants, $history),
            'weighted' => $this->executeWeightedTurn($participants),
            'custom' => $this->executeCustomTurn($participants, $group->settings),
            default => $this->executeRandomTurn($participants),
        };
    }

    private function executeRandomTurn(Collection $participants): Participant
    {
        return $participants->random();
    }

    private function executeRoundRobinTurn(Collection $participants, Collection $history): Participant
    {
        if ($history->isEmpty()) {
            return $participants->first();
        }

        $lastTurn = $history->first();
        $lastParticipantIndex = $participants->search(
            fn($p) => $p->id === $lastTurn->participant_id
        );

        $nextIndex = ($lastParticipantIndex + 1) % $participants->count();
        return $participants->values()[$nextIndex];
    }

    private function executeWeightedTurn(Collection $participants): Participant
    {
        $totalWeight = $participants->sum('weight');
        $random = rand(1, $totalWeight);

        $currentWeight = 0;
        foreach ($participants as $participant) {
            $currentWeight += $participant->weight ?? 1;
            if ($random <= $currentWeight) {
                return $participant;
            }
        }

        return $participants->first();
    }
}
```

## Database Design

### Core Models and Relationships

```php
// Group Model
class Group extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, HasRoles;

    protected $fillable = [
        'name', 'description', 'user_id', 'settings', 
        'is_public', 'invite_code', 'last_turn_at'
    ];

    protected $casts = [
        'settings' => 'array',
        'is_public' => 'boolean',
        'last_turn_at' => 'datetime',
    ];

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

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
                   ->withPivot(['role', 'joined_at'])
                   ->withTimestamps();
    }

    public function lastTurn(): HasOne
    {
        return $this->hasOne(Turn::class)->latest('created_at');
    }
}

// Participant Model
class Participant extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'group_id', 'name', 'weight', 'avatar', 
        'is_active', 'metadata'
    ];

    protected $casts = [
        'weight' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function turns(): HasMany
    {
        return $this->hasMany(Turn::class);
    }
}

// Turn Model
class Turn extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'group_id', 'participant_id', 'user_id', 
        'algorithm', 'metadata', 'executed_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'executed_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### Database Schema

```sql
-- Groups table
CREATE TABLE groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    settings JSON NOT NULL DEFAULT '{}',
    is_public BOOLEAN DEFAULT FALSE,
    invite_code VARCHAR(10) UNIQUE NULL,
    last_turn_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    
    INDEX idx_groups_user_id (user_id),
    INDEX idx_groups_is_public (is_public),
    INDEX idx_groups_invite_code (invite_code),
    INDEX idx_groups_created_at (created_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Participants table
CREATE TABLE participants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    weight INT DEFAULT 1,
    avatar VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_participants_group_id (group_id),
    INDEX idx_participants_is_active (is_active),
    
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
);

-- Turns table
CREATE TABLE turns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id BIGINT UNSIGNED NOT NULL,
    participant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    algorithm VARCHAR(50) NOT NULL,
    metadata JSON NULL,
    executed_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_turns_group_id (group_id),
    INDEX idx_turns_participant_id (participant_id),
    INDEX idx_turns_executed_at (executed_at),
    INDEX idx_turns_created_at (created_at),
    
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

## API Design

### RESTful API Structure

```php
// API Routes (routes/api.php)
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // Group management
    Route::apiResource('groups', GroupController::class);
    Route::post('groups/{group}/join', [GroupController::class, 'join']);
    Route::delete('groups/{group}/leave', [GroupController::class, 'leave']);
    
    // Participant management
    Route::apiResource('groups.participants', ParticipantController::class)
        ->shallow();
    
    // Turn execution
    Route::post('groups/{group}/turns', [TurnController::class, 'store']);
    Route::get('groups/{group}/turns', [TurnController::class, 'index']);
    Route::get('groups/{group}/analytics', [AnalyticsController::class, 'show']);
});

// Public routes
Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::get('groups/public', [GroupController::class, 'public']);
    Route::get('groups/join/{code}', [GroupController::class, 'joinByCode']);
});
```

### API Response Format

```php
// Standardized API responses
class ApiResponse
{
    public static function success($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    public static function error(string $message = 'Error', int $code = 400, $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}

// Usage in controllers
class GroupController extends Controller
{
    public function store(StoreGroupRequest $request): JsonResponse
    {
        $group = $this->createGroupAction->execute(
            $request->user(),
            $request->validated()
        );

        return ApiResponse::success(
            new GroupResource($group),
            'Group created successfully',
            201
        );
    }
}
```

## Frontend Integration (Inertia.js + React)

### Inertia.js Setup

```php
// Inertia middleware configuration
class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? new UserResource($request->user()) : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'permissions' => $request->user()?->getAllPermissions()->pluck('name') ?? [],
        ]);
    }
}
```

### React Component Structure

```typescript
// TypeScript interfaces
interface Group {
  id: string;
  name: string;
  description?: string;
  participant_count: number;
  settings: {
    algorithm: 'random' | 'round_robin' | 'weighted';
    allow_duplicates: boolean;
  };
  permissions: {
    can_edit: boolean;
    can_delete: boolean;
  };
}

// Inertia page component
export default function GroupsIndex({ groups }: { groups: PaginatedResponse<Group> }) {
  const { data, setData, get, processing } = useForm({
    search: '',
    algorithm: '',
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
          <Link href={route('groups.create')} className="btn-primary">
            Create Group
          </Link>
        </div>

        <SearchForm onSubmit={handleSearch} data={data} setData={setData} />
        <GroupsList groups={groups.data} />
        <Pagination links={groups.links} />
      </div>
    </AuthenticatedLayout>
  );
}
```

## Security Considerations

### Authentication & Authorization

```php
// Sanctum API authentication
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Protected routes
});

// Policy-based authorization
class GroupPolicy
{
    public function view(User $user, Group $group): bool
    {
        return $group->is_public || 
               $user->id === $group->user_id ||
               $user->hasRole('member', $group);
    }

    public function update(User $user, Group $group): bool
    {
        return $user->hasRole(['admin', 'moderator'], $group);
    }
}
```

### Data Validation

```php
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
            'algorithm' => ['required', 'in:random,round_robin,weighted'],
            'participants' => ['required', 'array', 'min:2', 'max:50'],
            'participants.*.name' => ['required', 'string', 'max:100'],
        ];
    }
}
```

This architecture ensures scalability, maintainability, and security while following Laravel best practices and modern development patterns.
