<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProfileAssigned
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, string>  $profileIds
     */
    public function __construct(
        public User $user,
        public array $profileIds,
        public ?string $ip = null,
    ) {}
}
