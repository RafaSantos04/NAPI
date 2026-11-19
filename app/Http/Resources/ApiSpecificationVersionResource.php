<?php

namespace App\Http\Resources;

use App\Models\ApiSpecificationVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Metadata of an imported version; the raw document never leaves through
 * the API (ADR-0014).
 *
 * @mixin ApiSpecificationVersion
 */
class ApiSpecificationVersionResource extends JsonResource
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
            'api_specification_id' => $this->api_specification_id,
            'number' => $this->number,
            'format' => $this->format,
            'openapi_version' => $this->openapi_version,
            'info_title' => $this->info_title,
            'info_version' => $this->info_version,
            'content_sha256' => $this->content_sha256,
            'size_bytes' => $this->size_bytes,
            'endpoints_count' => $this->whenCounted('endpoints'),
            'imported_by_membership_id' => $this->imported_by_membership_id,
            'created_at' => $this->created_at,
        ];
    }
}
