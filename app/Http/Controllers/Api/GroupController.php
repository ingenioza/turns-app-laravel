<?php

namespace App\Http\Controllers\Api;

use App\Application\Services\GroupService;
use App\Domain\Group\Group;
use App\Domain\User\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GroupController extends Controller
{
    public function __construct(
        private GroupService $groupService
    ) {}

    /**
     * Display a listing of user's groups
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        
        $groups = $this->groupService->getUserGroups($user);

        return response()->json([
            'groups' => $groups,
        ]);
    }

    /**
     * Store a newly created group
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'settings' => ['nullable', 'array'],
            'settings.turn_timeout' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'settings.auto_skip' => ['nullable', 'boolean'],
            'settings.notifications' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $group = $this->groupService->createGroup($user, $validated);

            return response()->json([
                'message' => 'Group created successfully',
                'group' => $group->load(['members', 'creator']),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Display the specified group
     */
    public function show(Request $request, Group $group): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Check if user is a member
        if (!$group->isMember($user)) {
            return response()->json([
                'message' => 'You are not a member of this group',
            ], 403);
        }

        return response()->json([
            'group' => $group->load(['members', 'creator', 'currentUser', 'activeTurn']),
        ]);
    }

    /**
     * Update the specified group
     */
    public function update(Request $request, Group $group): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Check if user is admin or creator
        if (!$group->isAdmin($user) && $group->creator_id !== $user->id) {
            return response()->json([
                'message' => 'Not authorized to update this group',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'settings' => ['nullable', 'array'],
        ]);

        try {
            $updatedGroup = $this->groupService->updateGroup($group, $validated);

            return response()->json([
                'message' => 'Group updated successfully',
                'group' => $updatedGroup->load(['members', 'creator']),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Remove the specified group
     */
    public function destroy(Request $request, Group $group): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Only creator can delete
        if ($group->creator_id !== $user->id) {
            return response()->json([
                'message' => 'Only the group creator can delete the group',
            ], 403);
        }

        try {
            $this->groupService->archiveGroup($group, $user);

            return response()->json([
                'message' => 'Group archived successfully',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Join a group by invite code
     */
    public function join(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invite_code' => ['required', 'string', 'size:8'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $group = $this->groupService->joinGroup($validated['invite_code'], $user);

            return response()->json([
                'message' => 'Joined group successfully',
                'group' => $group->load(['members', 'creator']),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Leave a group
     */
    public function leave(Request $request, Group $group): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $this->groupService->leaveGroup($group, $user);

            return response()->json([
                'message' => 'Left group successfully',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Remove a member from the group
     */
    public function removeMember(Request $request, Group $group, User $member): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $this->groupService->removeMember($group, $member, $user);

            return response()->json([
                'message' => 'Member removed successfully',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Update member role
     */
    public function updateMemberRole(Request $request, Group $group, User $member): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:admin,member'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $this->groupService->updateMemberRole($group, $member, $validated['role'], $user);

            return response()->json([
                'message' => 'Member role updated successfully',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get group members
     */
    public function members(Request $request, Group $group): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Check if user is a member
        if (!$group->isMember($user)) {
            return response()->json([
                'message' => 'You are not a member of this group',
            ], 403);
        }

        return response()->json([
            'members' => $group->getOrderedMembers(),
        ]);
    }

    /**
     * Search groups
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:3'],
        ]);

        $groups = $this->groupService->searchGroups($validated['query']);

        return response()->json([
            'groups' => $groups,
        ]);
    }
}
