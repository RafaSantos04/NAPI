<?php

namespace Database\Seeders;

use App\Models\SecurityLabResource;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SecurityLabSeeder extends Seeder
{
    /**
     * Seed the synthetic personas and documents of the Security Lab.
     *
     * The personas are rows in `users` because the protected scenario runs a
     * real Laravel Policy, which needs a real user. They are not accounts
     * anyone can use: inactive, no profile, a random password nobody knows
     * and an address under the reserved `.invalid` TLD.
     *
     * Does nothing while the lab is disabled, so its personas only exist
     * where the lab does. Safe to run again; nothing here is personal data.
     */
    public function run(): void
    {
        if (! config('security.lab.enabled')) {
            return;
        }

        $personas = [
            'Alice' => 'Documento A',
            'Bob' => 'Documento B',
        ];

        foreach ($personas as $name => $document) {
            $persona = User::firstOrCreate(
                ['email' => Str::lower($name).'@security-lab.invalid'],
                ['name' => "{$name} (Security Lab)", 'password' => Str::random(40), 'is_active' => false],
            );

            SecurityLabResource::firstOrCreate(
                ['owner_user_id' => $persona->id, 'name' => $document],
                ['content' => "Synthetic Security Lab Data — documento fictício de {$name}. Não contém dados reais."],
            );
        }
    }
}
