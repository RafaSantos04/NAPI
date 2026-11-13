<?php

namespace App\Domain\IAM\Actions;

use App\Domain\IAM\AuditContext;
use App\DTOs\AssignProfilesToUserDto;
use App\DTOs\CreateUserDto;
use App\Models\Profile;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class BootstrapAdministrator
{
    /**
     * @return array{user: User, created: bool}
     */
    public static function execute(string $name, string $email, string $password): array
    {
        return DB::transaction(function () use ($name, $email, $password): array {
            // Locking the system profile serializes concurrent first boots.
            $adminProfile = Profile::query()
                ->where('slug', Profile::ADMIN)
                ->lockForUpdate()
                ->firstOrFail();

            $existingAdministrator = $adminProfile->users()
                ->where('users.is_active', true)
                ->first();

            if ($existingAdministrator !== null) {
                return ['user' => $existingAdministrator, 'created' => false];
            }

            if (User::withTrashed()->where('email', $email)->exists()) {
                throw new DomainException('The bootstrap email already belongs to a non-administrator account.');
            }

            $context = AuditContext::system();
            $user = CreateUser::execute(new CreateUserDto($name, $email, $password), $context);

            AssignProfilesToUser::execute(
                $user,
                new AssignProfilesToUserDto([$adminProfile->id]),
                $context,
            );

            return ['user' => $user->refresh(), 'created' => true];
        });
    }
}
