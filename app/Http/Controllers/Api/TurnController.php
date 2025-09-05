<?php

namespace App\Http\Controllers\Api;

use App\Application\Services\TurnService;
use App\Domain\Group\Group;
use App\Domain\Turn\Turn;
use App\Domain\User\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TurnController extends Controller
{
    public function __construct(
        private TurnService $turnService
    ) {}

    /**
     * Display a listing of user's turns
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = $validated['limit'] ?? 50;
        $turns = $this->turnService->getUserHistory($user, $limit);

        return response()->json([
            'turns' => $turns,
        ]);
    }

    /**
     * Start a new turn
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_id' => ['required', 'exists:groups,id'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $group = Group::findOrFail($validated['group_id']);

        try {
            $turn = $this->turnService->startTurn($group, $user);

            return response()->json([
                'message' => 'Turn started successfully',
                'turn' => $turn->load(['user', 'group']),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Display the specified turn
     */
    public function show(Request $request, Turn $turn): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Check if user is a member of the group
        if (!$turn->group->isMember($user)) {
            return response()->json([
                'message' => 'You are not a member of this group',
            ], 403);
        }

        return response()->json([
            'turn' => $turn->load(['user', 'group']),
        ]);
    }

    /**
     * Complete a turn
     */
    public function complete(Request $request, Turn $turn): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
            'metadata' => ['nullable', 'array'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $completedTurn = $this->turnService->completeTurn($turn, $user, $validated);

            return response()->json([
                'message' => 'Turn completed successfully',
                'turn' => $completedTurn->load(['user', 'group']),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Skip a turn
     */
    public function skip(Request $request, Turn $turn): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $skippedTurn = $this->turnService->skipTurn($turn, $user, $validated['reason'] ?? null);

            return response()->json([
                'message' => 'Turn skipped successfully',
                'turn' => $skippedTurn->load(['user', 'group']),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Force end a turn (admin only)
     */
    public function forceEnd(Request $request, Turn $turn): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $endedTurn = $this->turnService->forceEndTurn($turn, $user, $validated['reason'] ?? null);

            return response()->json([
                'message' => 'Turn force ended successfully',
                'turn' => $endedTurn->load(['user', 'group']),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get active turn for a group
     */
    public function active(Request $request, Group $group): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Check if user is a member of the group
        if (!$group->isMember($user)) {
            return response()->json([
                'message' => 'You are not a member of this group',
            ], 403);
        }

        $activeTurn = $this->turnService->getActiveTurn($group);

        return response()->json([
            'active_turn' => $activeTurn ? $activeTurn->load(['user', 'group']) : null,
        ]);
    }

    /**
     * Get current turn for a group
     */
    public function current(Request $request, Group $group): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Check if user is a member of the group
        if (!$group->isMember($user)) {
            return response()->json([
                'message' => 'You are not a member of this group',
            ], 403);
        }

        $currentTurn = $this->turnService->getCurrentTurn($group);

        return response()->json([
            'current_turn' => $currentTurn ? $currentTurn->load(['user', 'group']) : null,
        ]);
    }

    /**
     * Get group turn history
     */
    public function groupHistory(Request $request, Group $group): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Check if user is a member of the group
        if (!$group->isMember($user)) {
            return response()->json([
                'message' => 'You are not a member of this group',
            ], 403);
        }

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = $validated['limit'] ?? 50;
        $history = $this->turnService->getGroupHistory($group, $limit);

        return response()->json([
            'history' => $history,
        ]);
    }

    /**
     * Get group statistics
     */
    public function groupStats(Request $request, Group $group): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Check if user is a member of the group
        if (!$group->isMember($user)) {
            return response()->json([
                'message' => 'You are not a member of this group',
            ], 403);
        }

        $stats = $this->turnService->getGroupStatistics($group);

        return response()->json([
            'statistics' => $stats,
        ]);
    }

    /**
     * Get user statistics
     */
    public function userStats(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $stats = $this->turnService->getUserStatistics($user);

        return response()->json([
            'statistics' => $stats,
        ]);
    }

    /**
     * Remove the specified turn from storage.
     */
    public function destroy(string $id)
    {
        // Turns shouldn't be deleted, only completed/skipped/expired
        return response()->json([
            'message' => 'Turns cannot be deleted',
        ], 405);
    }
}
