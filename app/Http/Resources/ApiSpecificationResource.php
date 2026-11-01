<?php

namespace App\Http\Resources;

use App\Models\ApiSpecification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApiSpecification
 */
class ApiSpecificationResource extends JsonResource
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
            'organization_id' => $this->organization_id,
            'application_id' => $this->application_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            // The specification's own state, as in the rest of the tree.
            'status' => $this->isArchived() ? 'archived' : 'active',
            'archived_at' => $this->archived_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
