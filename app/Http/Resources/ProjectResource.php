<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
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
            'type' => $this->type->value,
            'yield_unit' => $this->type->yieldUnit(),
            'owner_id' => $this->user_id,
            'is_owner' => $request->user() !== null && $this->isOwnedBy($request->user()),
            'current_month_yields_count' => $this->whenCounted('current_month_yields'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
