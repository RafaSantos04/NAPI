<?php

namespace App\Http\Resources;

use App\Models\Environment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Environment
 */
class EnvironmentResource extends JsonResource
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
            'responsible_membership_id' => $this->responsible_membership_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'classification' => $this->classification->value,
            'authorized_scope' => $this->authorized_scope,
            'authorization_reference' => $this->authorization_reference,
            // The environment's own state, as for applications.
            'status' => $this->isArchived() ? 'archived' : 'active',
            'archived_at' => $this->archived_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
