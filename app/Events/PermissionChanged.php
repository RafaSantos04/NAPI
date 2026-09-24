<?php

namespace App\Events;

use App\Models\Profile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PermissionChanged
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int|string, array{can_view: bool, can_create: bool, can_update: bool, can_delete: bool}>  $permissions
     */
    public function __construct(
        public Profile $profile,
        public array $permissions,
        public ?string $ip = null,
    ) {}
}
