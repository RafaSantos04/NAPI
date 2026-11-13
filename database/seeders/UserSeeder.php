<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Demo accounts are local fixtures. Anywhere else, one `db:seed --force`
     * must never create an administrator (FIND-038).
     *
     * @var list<string>
     */
    public const ENVIRONMENTS = ['local', 'testing'];

    private const PASSWORD_MIN_CHARACTERS = 12;

    // Bcrypt ignores bytes after the 72nd; refusing them avoids two different
    // configured values authenticating as the same password.
    private const PASSWORD_MAX_BYTES = 72;

    /**
     * Seed the application's demo users.
     */
    public function run(): void
    {
        if (! app()->environment(self::ENVIRONMENTS)) {
            throw new RuntimeException('Demo users are only seeded in local and testing.');
        }

        $password = $this->password();
        $admin = Profile::where('slug', 'admin')->firstOrFail();
        $dev = Profile::where('slug', 'dev')->firstOrFail();
        $viewer = Profile::where('slug', 'viewer')->firstOrFail();

        $adminUser = User::updateOrCreate(
            ['email' => 'admin@napi.dev'],
            [
                'name' => 'Admin User',
                'password' => Hash::make($password),
                'is_active' => true,
            ],
        );
        $adminUser->profiles()->sync([$admin->id => ['assigned_by' => $adminUser->id]]);

        $devUser = User::updateOrCreate(
            ['email' => 'dev@napi.dev'],
            [
                'name' => 'Dev User',
                'password' => Hash::make($password),
                'is_active' => true,
            ],
        );
        $devUser->profiles()->sync([$dev->id => ['assigned_by' => $adminUser->id]]);

        $viewerUser = User::updateOrCreate(
            ['email' => 'viewer@napi.dev'],
            [
                'name' => 'Viewer User',
                'password' => Hash::make($password),
                'is_active' => true,
            ],
        );
        $viewerUser->profiles()->sync([$viewer->id => ['assigned_by' => $adminUser->id]]);
    }

    private function password(): string
    {
        $password = config('security.seed.user_password');

        if (! is_string($password) || trim($password) === '') {
            throw new RuntimeException('NAPI_SEED_USER_PASSWORD must be set before seeding demo users.');
        }

        $characters = mb_strlen($password);
        $bytes = strlen($password);

        if ($characters < self::PASSWORD_MIN_CHARACTERS || $bytes > self::PASSWORD_MAX_BYTES) {
            throw new RuntimeException('NAPI_SEED_USER_PASSWORD must contain at least 12 characters and at most 72 bytes.');
        }

        return $password;
    }
}
