<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'creator_id' => $this->creator_id,
            'invite_code' => $this->invite_code,
            'status' => $this->status,
            'settings' => $this->settings,
            'last_turn_at' => $this->last_turn_at,
            'member_count' => $this->whenLoaded('members', function () {
                return $this->members->count();
            }),
            'active_member_count' => $this->whenLoaded('activeMembers', function () {
                return $this->activeMembers->count();
            }),
            'creator' => $this->whenLoaded('creator'),
            'active_members' => $this->whenLoaded('activeMembers'),
            'turns' => $this->whenLoaded('turns'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
