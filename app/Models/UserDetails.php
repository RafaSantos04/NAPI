<?php

namespace App\Models;

use Database\Factories\UserDetailsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'cpf_hash',
    'cpf_verified_at',
    'phone',
    'phone_verified_at',
    'cep',
    'street',
    'number',
    'complement',
    'neighborhood',
    'city',
    'state',
    'country',
    'data_consent_at',
    'data_export_requested_at',
    'data_deletion_requested_at',
])]
class UserDetails extends Model
{
    /** @use HasFactory<UserDetailsFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'cpf_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'data_consent_at' => 'datetime',
            'data_export_requested_at' => 'datetime',
            'data_deletion_requested_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashCpf(string $cpf): string
    {
        return hash('sha256', preg_replace('/\D/', '', $cpf));
    }

    public function verifyCpf(string $cpf): bool
    {
        return $this->cpf_hash === self::hashCpf($cpf);
    }
}
