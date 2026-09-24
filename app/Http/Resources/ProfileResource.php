<?php

namespace App\Http\Resources;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Profile
 */
class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'menus' => $this->whenLoaded('menus', function () {
                return $this->menus->map(fn ($menu) => [
                    'id' => $menu->id,
                    'label' => $menu->label,
                    'route_name' => $menu->route_name,
                    'permissions' => [
                        'can_view' => $menu->pivot->can_view,
                        'can_create' => $menu->pivot->can_create,
                        'can_update' => $menu->pivot->can_update,
                        'can_delete' => $menu->pivot->can_delete,
                    ],
                ]);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
