<?php

namespace App\Http\Resources;

use App\Models\ApiEndpoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApiEndpoint
 */
class ApiEndpointResource extends JsonResource
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
            'api_specification_version_id' => $this->api_specification_version_id,
            'method' => $this->method,
            'path' => $this->path,
            'operation_id' => $this->operation_id,
            'summary' => $this->summary,
        ];
    }
}
