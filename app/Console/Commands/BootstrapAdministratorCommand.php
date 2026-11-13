<?php

namespace App\Console\Commands;

use App\Domain\IAM\Actions\BootstrapAdministrator;
use App\Models\Profile;
use App\Models\User;
use DomainException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('napi:bootstrap-admin')]
#[Description('Create the first production administrator without rotating existing credentials')]
class BootstrapAdministratorCommand extends Command
{
    private const PASSWORD_MAX_BYTES = 72;

    public function handle(): int
    {
        if ($this->activeAdministratorExists()) {
            $this->components->info('An active administrator already exists; bootstrap was skipped.');

            return self::SUCCESS;
        }

        $name = config('security.bootstrap.admin_name');
        $email = config('security.bootstrap.admin_email');
        $password = config('security.bootstrap.admin_password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:12'],
            ],
            attributes: [
                'name' => 'NAPI_BOOTSTRAP_ADMIN_NAME',
                'email' => 'NAPI_BOOTSTRAP_ADMIN_EMAIL',
                'password' => 'NAPI_BOOTSTRAP_ADMIN_PASSWORD',
            ],
        );

        $validator->after(function ($validator) use ($password): void {
            if (is_string($password) && strlen($password) > self::PASSWORD_MAX_BYTES) {
                $validator->errors()->add(
                    'password',
                    'NAPI_BOOTSTRAP_ADMIN_PASSWORD must contain at most 72 bytes.',
                );
            }
        });

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::INVALID;
        }

        try {
            $result = BootstrapAdministrator::execute(
                trim((string) $name),
                Str::lower(trim((string) $email)),
                (string) $password,
            );
        } catch (DomainException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $message = $result['created']
            ? 'The first administrator was created successfully.'
            : 'An active administrator already exists; bootstrap was skipped.';

        $this->components->info($message);

        return self::SUCCESS;
    }

    private function activeAdministratorExists(): bool
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('profiles', fn ($query) => $query->where('slug', Profile::ADMIN))
            ->exists();
    }
}
