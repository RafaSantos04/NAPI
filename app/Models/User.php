<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasOne<UserDetails, $this>
     */
    public function details(): HasOne
    {
        return $this->hasOne(UserDetails::class);
    }

    /**
     * @return BelongsToMany<Profile, $this, UserProfile>
     */
    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'user_profiles')
            ->using(UserProfile::class)
            ->withPivot('assigned_by', 'assigned_at');
    }

    /**
     * @return HasMany<UserProfile, $this>
     */
    public function assignedProfiles(): HasMany
    {
        return $this->hasMany(UserProfile::class, 'assigned_by');
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function hasProfile(string $slug): bool
    {
        // Accessing the relation as a property (not calling profiles()) lets
        // Eloquent cache it on this model instance, so repeated Policy/
        // middleware checks within the same request don't re-query the DB.
        return $this->profiles->contains(fn (Profile $profile) => $profile->slug === $slug);
    }

    public function hasPermission(string $routeName, string $action): bool
    {
        // Implementado na Fase 2, aqui é só stub
        return true;
    }
}
