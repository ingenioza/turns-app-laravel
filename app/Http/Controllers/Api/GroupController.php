<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Group\CreateGroupRequest;
use App\Http\Requests\Group\IndexGroupRequest;
use App\Http\Requests\Group\JoinGroupRequest;
use App\Http\Requests\Group\UpdateGroupRequest;
use App\Http\Requests\Group\UpdateMemberRoleRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class GroupController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of user's groups
     */
    public function index(IndexGroupRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Group::class);

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
    public function store(CreateGroupRequest $request): JsonResponse
    {
        $this->authorize('create', Group::class);

        $validated = $request->validated();

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
        $this->authorize('view', $group);

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
    public function update(UpdateGroupRequest $request, Group $group): JsonResponse
    {
        $this->authorize('update', $group);

        $validated = $request->validated();

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
        $this->authorize('delete', $group);

        $group->delete();

        return response()->json([
            'message' => 'Group deleted successfully',
        ]);
    }

    /**
     * Join a group by invite code
     */
    public function join(JoinGroupRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $group = Group::where('invite_code', $validated['invite_code'])
            ->where('status', 'active')
            ->first();

        if (! $group) {
            return response()->json(['message' => 'Invalid invite code'], 404);
        }

        $this->authorize('join', $group);

        /** @var User $user */
        $user = $request->user();

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
        $this->authorize('leave', $group);

        /** @var User $user */
        $user = $request->user();

        $group->members()->detach($user->id);

        return response()->json([
            'message' => 'Successfully left group',
        ]);
    }

    /**
     * Search for groups
     */
    public function search(IndexGroupRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $query = Group::query();

        if (!empty($validated['q'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('name', 'like', '%' . $validated['q'] . '%')
                  ->orWhere('description', 'like', '%' . $validated['q'] . '%');
            });
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $groups = $query->with(['activeMembers'])
            ->orderBy('updated_at', 'desc')
            ->paginate(15);

        return GroupResource::collection($groups);
    }

    /**
     * Get group members
     */
    public function members(Request $request, Group $group): JsonResponse
    {
        $this->authorize('view', $group);

        $members = $group->members()
            ->withPivot(['role', 'joined_at', 'is_active', 'turn_order', 'settings'])
            ->orderBy('pivot_turn_order')
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->pivot->role,
                    'joined_at' => $member->pivot->joined_at,
                    'is_active' => $member->pivot->is_active,
                    'turn_order' => $member->pivot->turn_order,
                    'settings' => $member->pivot->settings,
                ];
            });

        return response()->json([
            'members' => $members,
        ]);
    }

    /**
     * Remove a member from the group
     */
    public function removeMember(Request $request, Group $group, User $member): JsonResponse
    {
        $this->authorize('manageMembers', $group);

        // Cannot remove the creator
        if ($group->creator_id === $member->id) {
            return response()->json(['message' => 'Cannot remove group creator'], 400);
        }

        // Check if member is actually in the group
        if (!$group->members->contains($member)) {
            return response()->json(['message' => 'User is not a member of this group'], 400);
        }

        $group->members()->detach($member->id);

        return response()->json([
            'message' => 'Member removed successfully',
        ]);
    }

    /**
     * Update member role
     */
    public function updateMemberRole(UpdateMemberRoleRequest $request, Group $group, User $member): JsonResponse
    {
        $this->authorize('manageMembers', $group);

        $validated = $request->validated();

        // Cannot change creator role
        if ($group->creator_id === $member->id) {
            return response()->json(['message' => 'Cannot change creator role'], 400);
        }

        // Check if member is actually in the group
        if (!$group->members->contains($member)) {
            return response()->json(['message' => 'User is not a member of this group'], 400);
        }

        $group->members()->updateExistingPivot($member->id, [
            'role' => $validated['role'],
        ]);

        return response()->json([
            'message' => 'Member role updated successfully',
        ]);
    }
}
