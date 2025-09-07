<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Group\CreateGroupRequest;
use App\Http\Requests\Group\JoinGroupRequest;
use App\Http\Requests\Group\UpdateGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of user's groups
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Group::class);

        /** @var User $user */
        $user = $request->user();

        $groups = $user->groups()
            ->with(['activeMembers'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return Inertia::render('Groups/Index', [
            'groups' => GroupResource::collection($groups),
        ]);
    }

    /**
     * Show the form for creating a new group
     */
    public function create(): Response
    {
        $this->authorize('create', Group::class);

        return Inertia::render('Groups/Create');
    }

    /**
     * Store a newly created group
     */
    public function store(CreateGroupRequest $request)
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
            'turn_order' => 1,
            'joined_at' => now(),
        ]);

        return redirect()->route('groups.show', $group)
            ->with('success', 'Group created successfully!');
    }

    /**
     * Display the specified group
     */
    public function show(Group $group): Response
    {
        $this->authorize('view', $group);

        $group->load(['creator', 'activeMembers', 'turns' => function ($query) {
            $query->with('user')->latest()->take(10);
        }]);

        return Inertia::render('Groups/Show', [
            'group' => new GroupResource($group),
        ]);
    }

    /**
     * Show the form for editing the specified group
     */
    public function edit(Group $group): Response
    {
        $this->authorize('update', $group);

        return Inertia::render('Groups/Edit', [
            'group' => new GroupResource($group),
        ]);
    }

    /**
     * Update the specified group
     */
    public function update(UpdateGroupRequest $request, Group $group)
    {
        $this->authorize('update', $group);

        $validated = $request->validated();
        $group->update($validated);

        return redirect()->route('groups.show', $group)
            ->with('success', 'Group updated successfully!');
    }

    /**
     * Show the join group form
     */
    public function join(): Response
    {
        return Inertia::render('Groups/Join');
    }

    /**
     * Join a group by invite code
     */
    public function processJoin(JoinGroupRequest $request)
    {
        $validated = $request->validated();

        $group = Group::where('invite_code', $validated['invite_code'])
            ->where('status', 'active')
            ->first();

        if (! $group) {
            return back()->withErrors(['invite_code' => 'Invalid invite code.']);
        }

        $this->authorize('join', $group);

        /** @var User $user */
        $user = $request->user();

        // Check if user is already a member
        if ($group->members()->where('users.id', $user->id)->exists()) {
            return redirect()->route('groups.show', $group)
                ->with('info', 'You are already a member of this group.');
        }

        // Add user to group
        $nextTurnOrder = $group->members()->max('turn_order') + 1;
        $group->members()->attach($user->id, [
            'role' => 'member',
            'turn_order' => $nextTurnOrder,
            'joined_at' => now(),
        ]);

        return redirect()->route('groups.show', $group)
            ->with('success', 'Successfully joined the group!');
    }

    /**
     * Remove the specified group
     */
    public function destroy(Group $group)
    {
        $this->authorize('delete', $group);

        $group->delete();

        return redirect()->route('groups.index')
            ->with('success', 'Group deleted successfully!');
    }
}
