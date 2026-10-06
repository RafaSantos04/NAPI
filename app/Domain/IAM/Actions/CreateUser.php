<?php

namespace App\Domain\IAM\Actions;

use App\Domain\IAM\AuditContext;
use App\DTOs\CreateUserDto;
use App\Events\UserCreated;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Shared by the API and the admin area, so both create accounts from the same
 * fixed set of attributes and record the same audit entry (Phase 4.2).
 * The password is hashed by the model cast and never reaches the audit log.
 */
class CreateUser
{
    public static function execute(CreateUserDto $dto, AuditContext $context): User
    {
        return DB::transaction(function () use ($dto, $context) {
            $user = User::create([
                'name' => $dto->name,
                'email' => $dto->email,
                'password' => $dto->password,
            ]);

            event(new UserCreated($user, $context));

            return $user;
        });
    }
}
