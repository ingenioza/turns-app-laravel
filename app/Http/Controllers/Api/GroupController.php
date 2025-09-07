<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class GroupController extends Controller
{
    /**
     * Display a listing of user's groups
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $groups = $user->groups()
            ->with(['activeMembers'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return GroupResource::collection($groups);
    }

    /**
     * Store a newly created group
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'settings' => 'nullable|array',
            'settings.turn_duration' => 'nullable|integer|min:1|max:1440',
            'settings.notifications_enabled' => 'nullable|boolean',
            'settings.auto_advance' => 'nullable|boolean',
        ]);

        /** @var User $user */
        $user = $request->user();

        $group = Group::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'creator_id' => $user->id,
            'settings' => $validated['settings'] ?? [],
            'status' => 'active',
        ]);

        // Add creator as admin member
        $group->members()->attach($user->id, [
            'role' => 'admin',
            'joined_at' => now(),
            'is_active' => true,
            'turn_order' => 1,
        ]);

        return response()->json([
            'message' => 'Group created successfully',
            'group' => new GroupResource($group->load('activeMembers')),
        ], 201);
    }

    /**
     * Display the specified group
     */
    public function show(Request $request, Group $group): JsonResponse
    {
        // Check if user is a member of the group
        /** @var User $user */
        $user = $request->user();

        if (! $group->members->contains($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $group->load(['creator', 'activeMembers', 'turns' => function ($query) {
            $query->orderBy('started_at', 'desc')->limit(10);
        }]);

        return response()->json([
            'group' => new GroupResource($group),
        ]);
    }

    /**
     * Update the specified group
     */
    public function update(Request $request, Group $group): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Check if user is admin of the group
        $membership = $group->members()->where('user_id', $user->id)->first();
        if (! $membership || $membership->pivot->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'archived'])],
            'settings' => 'nullable|array',
            'settings.turn_duration' => 'nullable|integer|min:1|max:1440',
            'settings.notifications_enabled' => 'nullable|boolean',
            'settings.auto_advance' => 'nullable|boolean',
        ]);

        $group->update($validated);

        return response()->json([
            'message' => 'Group updated successfully',
            'group' => new GroupResource($group->fresh(['activeMembers'])),
        ]);
    }

    /**
     * Remove the specified group
     */
    public function destroy(Request $request, Group $group): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Only creator can delete the group
        if ($group->creator_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $group->delete();

        return response()->json([
            'message' => 'Group deleted successfully',
        ]);
    }

    /**
     * Join a group by invite code
     */
    public function join(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invite_code' => 'required|string|size:8',
        ]);

        $group = Group::where('invite_code', $validated['invite_code'])
            ->where('status', 'active')
            ->first();

        if (! $group) {
            return response()->json(['message' => 'Invalid invite code'], 404);
        }

        /** @var User $user */
        $user = $request->user();

        // Check if user is already a member
        if ($group->members->contains($user)) {
            return response()->json(['message' => 'Already a member of this group'], 400);
        }

        // Add user as member
        $nextTurnOrder = $group->members()->max('turn_order') + 1;
        $group->members()->attach($user->id, [
            'role' => 'member',
            'joined_at' => now(),
            'is_active' => true,
            'turn_order' => $nextTurnOrder,
        ]);

        return response()->json([
            'message' => 'Successfully joined group',
            'group' => new GroupResource($group->load('activeMembers')),
        ]);
    }

    /**
     * Leave a group
     */
    public function leave(Request $request, Group $group): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Check if user is a member
        if (! $group->members->contains($user)) {
            return response()->json(['message' => 'Not a member of this group'], 400);
        }

        // Creator cannot leave their own group
        if ($group->creator_id === $user->id) {
            return response()->json(['message' => 'Group creator cannot leave the group'], 400);
        }

        $group->members()->detach($user->id);

        return response()->json([
            'message' => 'Successfully left group',
        ]);
    }
}
