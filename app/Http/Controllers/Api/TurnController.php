<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Turn\CompleteTurnRequest;
use App\Http\Requests\Turn\CreateTurnRequest;
use App\Http\Requests\Turn\ForceEndTurnRequest;
use App\Http\Requests\Turn\SkipTurnRequest;
use App\Http\Resources\TurnResource;
use App\Models\Group;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TurnController extends Controller
{
    /**
     * Get all turns for a user or specific group
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $groupId = $request->query('group_id');

        $query = Turn::with(['user', 'group']);

        if ($groupId) {
            // Verify user has access to this group
            $group = Group::findOrFail($groupId);
            if (!$group->members()->where('users.id', $user->id)->exists() && $group->creator_id !== $user->id) {
                return response()->json(['message' => 'You do not have access to this group'], 403);
            }
            $query->where('group_id', $groupId);
        } else {
            // Get all turns for groups user is part of
            $groupIds = $user->groups()->pluck('groups.id')
                ->merge($user->createdGroups()->pluck('id'));
            $query->whereIn('group_id', $groupIds);
        }

        $turns = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            // Tests expect the top-level collection key to be 'data'
            'data' => TurnResource::collection($turns),
            'meta' => [
                'current_page' => $turns->currentPage(),
                'last_page' => $turns->lastPage(),
                'per_page' => $turns->perPage(),
                'total' => $turns->total(),
            ]
        ]);
    }

    /**
     * Create a new turn
     */
    public function store(CreateTurnRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $group = Group::findOrFail($validated['group_id']);

        // Check if user is part of the group
        if (!$group->members()->where('users.id', $request->user()->id)->exists() && 
            $group->creator_id !== $request->user()->id) {
            return response()->json(['message' => 'You are not a member of this group'], 403);
        }

        // Check if there's already an active turn in this group
        if ($group->turns()->where('status', 'active')->exists()) {
            return response()->json(['message' => 'There is already an active turn in this group'], 422);
        }

        $turn = Turn::create([
            'group_id' => $validated['group_id'],
            'user_id' => $request->user()->id,
            'status' => 'active',
            'started_at' => now(),
            'notes' => $validated['notes'],
            'metadata' => $validated['metadata'] ?? [],
        ]);

        return response()->json([
            'message' => 'Turn started successfully',
            'turn' => new TurnResource($turn->load(['user', 'group'])),
        ], 201);
    }

    /**
     * Get a specific turn
     */
    public function show(Request $request, Turn $turn): JsonResponse
    {
        // Check if user has access to this turn
        $user = $request->user();
        if (!$turn->group->members()->where('users.id', $user->id)->exists() && 
            $turn->group->creator_id !== $user->id) {
            return response()->json(['message' => 'You do not have access to this turn'], 403);
        }

        return response()->json([
            'turn' => new TurnResource($turn->load(['user', 'group'])),
        ]);
    }

    /**
     * Complete a turn
     */
    public function complete(CompleteTurnRequest $request, Turn $turn): JsonResponse
    {
        // Check if user owns this turn or is admin
        if ($turn->user_id !== $request->user()->id && $turn->group->creator_id !== $request->user()->id) {
            return response()->json(['message' => 'You can only complete your own turns or as a group admin'], 403);
        }

        if ($turn->status !== 'active') {
            return response()->json(['message' => 'Turn is not active'], 422);
        }

        $validated = $request->validated();

        $endedAt = now();
        $durationSeconds = $turn->started_at ? $endedAt->diffInSeconds($turn->started_at) : 0;

        $turn->update([
            'status' => 'completed',
            'ended_at' => $endedAt,
            'duration_seconds' => $durationSeconds,
            'notes' => $validated['notes'] ?? $turn->notes,
            'metadata' => array_merge($turn->metadata ?? [], $validated['metadata'] ?? []),
        ]);

        return response()->json([
            'message' => 'Turn completed successfully',
            'turn' => new TurnResource($turn->load(['user', 'group'])),
        ]);
    }

    /**
     * Skip a turn
     */
    public function skip(SkipTurnRequest $request, Turn $turn): JsonResponse
    {
        // Check if user owns this turn or is a group admin
        if ($turn->user_id !== $request->user()->id && $turn->group->creator_id !== $request->user()->id) {
            return response()->json(['message' => 'You can only skip your own turns or as a group admin'], 403);
        }

        if ($turn->status !== 'active') {
            return response()->json(['message' => 'Turn is not active'], 422);
        }

        $validated = $request->validated();

        $endedAt = now();
        $durationSeconds = $turn->started_at ? $endedAt->diffInSeconds($turn->started_at) : 0;

        $turn->update([
            'status' => 'skipped',
            'ended_at' => $endedAt,
            'duration_seconds' => $durationSeconds,
            'notes' => $validated['reason'],
            'metadata' => array_merge($turn->metadata ?? [], [
                'skip_reason' => $validated['reason'] ?? 'No reason provided',
                'skipped_by' => $request->user()->id,
            ]),
        ]);

        return response()->json([
            'message' => 'Turn skipped successfully',
            'turn' => new TurnResource($turn->load(['user', 'group'])),
        ]);
    }

    /**
     * Force end a turn (admin only)
     */
    public function forceEnd(ForceEndTurnRequest $request, Turn $turn): JsonResponse
    {
        // Check if user is group admin
        if ($turn->group->creator_id !== $request->user()->id) {
            return response()->json(['message' => 'Only group admins can force end turns'], 403);
        }

        if ($turn->status !== 'active') {
            return response()->json(['message' => 'Turn is not active'], 422);
        }

        $validated = $request->validated();

        $endedAt = now();
        $durationSeconds = $turn->started_at ? $endedAt->diffInSeconds($turn->started_at) : 0;

        $turn->update([
            'status' => 'expired',
            'ended_at' => $endedAt,
            'duration_seconds' => $durationSeconds,
            'notes' => $validated['reason'],
            'metadata' => array_merge($turn->metadata ?? [], [
                'force_end_reason' => $validated['reason'],
                'force_ended_by' => $request->user()->id,
            ]),
        ]);

        return response()->json([
            'message' => 'Turn force-ended successfully',
            'turn' => new TurnResource($turn->load(['user', 'group'])),
        ]);
    }

    /**
     * Get active turns for user or active turn for specific group
     */
    public function active(Request $request, Group $group = null): JsonResponse
    {
        $user = $request->user();

        if ($group) {
            // Group-specific active turn (called via /groups/{group}/turns/active)
            if (!$group->members()->where('users.id', $user->id)->exists() && $group->creator_id !== $user->id) {
                return response()->json(['message' => 'You do not have access to this group'], 403);
            }

            $activeTurn = $group->turns()->where('status', 'active')->with(['user'])->first();

            return response()->json([
                'active_turn' => $activeTurn ? new TurnResource($activeTurn) : null,
            ]);
        } else {
            // User's active turns across all groups (called via /turns/active)
            $groupIds = $user->groups()->pluck('groups.id')
                ->merge($user->createdGroups()->pluck('id'));
            
            $turns = Turn::with(['user', 'group'])
                ->where('status', 'active')
                ->whereIn('group_id', $groupIds)
                ->orderBy('started_at', 'asc')
                ->get();

            return response()->json([
                'turns' => TurnResource::collection($turns),
            ]);
        }
    }

    /**
     * Get current turn for a specific group
     */
    public function current(Request $request, Group $group): JsonResponse
    {
        // Check if user has access to this group
        if (!$group->members()->where('users.id', $request->user()->id)->exists() && 
            $group->creator_id !== $request->user()->id) {
            return response()->json(['message' => 'You do not have access to this group'], 403);
        }

        $currentTurn = $group->turns()->where('status', 'active')->with(['user'])->first();

        $groupMembers = $group->members()
            ->where('is_active', true)
            ->orderBy('turn_order')
            ->get()
            ->map(function($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'turn_order' => $member->pivot->turn_order,
                ];
            });

        if (!$currentTurn) {
            return response()->json([
                'active_turn' => null,
                'next_user' => $this->getNextUser($group),
                'group_members' => $groupMembers,
            ]);
        }

        return response()->json([
            'active_turn' => new TurnResource($currentTurn),
            'next_user' => $this->getNextUser($group),
            'group_members' => $groupMembers,
        ]);
    }

    /**
     * Get turn history for a specific group
     */
    public function history(Request $request, Group $group): JsonResponse
    {
        // Check if user has access to this group
        if (!$group->members()->where('users.id', $request->user()->id)->exists() && 
            $group->creator_id !== $request->user()->id) {
            return response()->json(['message' => 'You do not have access to this group'], 403);
        }

        $turns = $group->turns()
            ->whereIn('status', ['completed', 'skipped', 'expired'])
            ->with(['user'])
            ->orderBy('ended_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => TurnResource::collection($turns),
            'meta' => [
                'current_page' => $turns->currentPage(),
                'last_page' => $turns->lastPage(),
                'per_page' => $turns->perPage(),
                'total' => $turns->total(),
            ]
        ]);
    }

    /**
     * Get user turn statistics
     */
    public function userStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $groupId = $request->query('group_id');
        
        $query = $user->turns();
        
        if ($groupId) {
            $query->where('group_id', $groupId);
        }

        // Use cloned base query to avoid cumulative where conditions mutating the original
        $base = $user->turns();
        if ($groupId) {
            $base = $base->where('group_id', $groupId);
        }

        $totalTurns = (clone $base)->count();
        $completedTurns = (clone $base)->where('status', 'completed')->count();
        $skippedTurns = (clone $base)->where('status', 'skipped')->count();
        $activeTurns = (clone $base)->where('status', 'active')->count();
        $totalDurationSeconds = (clone $base)->where('status', 'completed')->sum('duration_seconds');
        $averageDurationSeconds = $completedTurns > 0
            ? (clone $base)->where('status', 'completed')->avg('duration_seconds')
            : 0;

        $stats = [
            'total_turns' => $totalTurns,
            'completed_turns' => $completedTurns,
            'skipped_turns' => $skippedTurns,
            'active_turns' => $activeTurns,
            'total_duration_seconds' => (int) $totalDurationSeconds,
            'average_duration_seconds' => (int) round($averageDurationSeconds),
            'total_duration_formatted' => $this->formatDuration($totalDurationSeconds),
            'average_duration_formatted' => $this->formatDuration($averageDurationSeconds),
        ];

        return response()->json(['user_stats' => $stats]);
    }

    /**
     * Get group turn statistics
     */
    public function groupStats(Request $request, Group $group): JsonResponse
    {
        // Check if user has access to this group
        if (!$group->members()->where('users.id', $request->user()->id)->exists() && 
            $group->creator_id !== $request->user()->id) {
            return response()->json(['message' => 'You do not have access to this group'], 403);
        }

        $stats = [
            'total_turns' => $group->turns()->count(),
            'completed_turns' => $group->turns()->where('status', 'completed')->count(),
            'skipped_turns' => $group->turns()->where('status', 'skipped')->count(),
            'expired_turns' => $group->turns()->where('status', 'expired')->count(),
            'average_duration' => $this->formatDuration($group->turns()->where('status', 'completed')->avg('duration_seconds')),
            'total_time' => $this->formatDuration($group->turns()->where('status', 'completed')->sum('duration_seconds')),
            'total_members' => $group->members()->count(),
            'active_members' => $group->members()->where('is_active', true)->count(),
        ];

        // Get member statistics
        $memberStats = $group->members()->with(['turns' => function($query) use ($group) {
            $query->where('group_id', $group->id);
        }])->get()->map(function($member) {
            return [
                'user_id' => $member->id,
                'name' => $member->name,
                'total_turns' => $member->turns->count(),
                'completed_turns' => $member->turns->where('status', 'completed')->count(),
                'total_duration' => $member->turns->where('status', 'completed')->sum('duration_seconds'),
            ];
        });

        return response()->json([
            'group_stats' => $stats,
            'member_stats' => $memberStats,
        ]);
    }

    /**
     * Get the next user in the turn order
     */
    private function getNextUser(Group $group): ?array
    {
        $activeMembers = $group->members()
            ->where('is_active', true)
            ->orderBy('turn_order')
            ->get();

        if ($activeMembers->isEmpty()) {
            return null;
        }

        $lastTurn = $group->turns()
            ->whereIn('status', ['completed', 'skipped', 'expired'])
            ->orderBy('ended_at', 'desc')
            ->first();

        if (!$lastTurn) {
            // No previous turns, start with first member
            $nextUser = $activeMembers->first();
        } else {
            // Find the current user's position and get next
            $currentUserOrder = $activeMembers->where('id', $lastTurn->user_id)->first()?->pivot?->turn_order;
            
            if ($currentUserOrder === null) {
                $nextUser = $activeMembers->first();
            } else {
                $nextUser = $activeMembers->where('turn_order', '>', $currentUserOrder)->first()
                    ?? $activeMembers->first(); // Wrap around to first user
            }
        }

        return $nextUser ? [
            'id' => $nextUser->id,
            'name' => $nextUser->name,
            'turn_order' => $nextUser->pivot->turn_order,
        ] : null;
    }

    /**
     * Format duration from seconds to human readable format
     */
    private function formatDuration(?float $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $seconds = round($seconds);
        
        if ($seconds < 60) {
            return "{$seconds}s";
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            return $remainingSeconds > 0 ? "{$minutes}m {$remainingSeconds}s" : "{$minutes}m";
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $remainingSeconds = $seconds % 60;
            
            $result = "{$hours}h";
            if ($minutes > 0) $result .= " {$minutes}m";
            if ($remainingSeconds > 0) $result .= " {$remainingSeconds}s";
            
            return $result;
        }
    }
}
