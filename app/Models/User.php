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
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable, SoftDeletes;

    protected static function booted(): void
    {
        // Hygiene only: authentication already rejects tokens of inactive
        // users (AppServiceProvider), so security does not depend on this.
        static::updated(function (User $user) {
            if ($user->wasChanged('is_active') && ! $user->is_active) {
                $user->tokens()->delete();
            }
        });
    }

    /**
     * No "remember me": users has no remember_token column. An empty name
     * makes the session guard skip the token on logout instead of reading a
     * missing attribute (strict mode).
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

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

    public function isAdministrator(): bool
    {
        return $this->hasProfile(Profile::ADMIN);
    }

    /**
     * Functional permission check against the profile x menu matrix, e.g.
     * hasPermission('users.update'). The single answer used by the route
     * middleware, the Policies and the navigation tree (ADR-0008).
     *
     * The admin profile holds every functional permission, including ones
     * added after it was seeded. Contextual Policy rules and domain
     * invariants still apply to it.
     */
    public function hasPermission(string $permission): bool
    {
        $parts = explode('.', $permission);

        if (count($parts) !== 2 || ! in_array($parts[1], MenuProfile::ACTIONS, true)) {
            return false;
        }

        if ($this->isAdministrator()) {
            return true;
        }

        return $this->permissionKeys()->contains($permission);
    }

    /**
     * Union of the functional permissions granted by all of the user's
     * profiles. Loaded once per model instance.
     *
     * @return Collection<int, string>
     */
    public function permissionKeys(): Collection
    {
        $this->loadMissing('profiles.menus');

        return $this->profiles
            ->flatMap(fn (Profile $profile) => $profile->permissionKeys())
            ->unique()
            ->values();
    }

    /**
     * Whether granting $profile to someone gives them nothing this user does
     * not already have. Only an administrator can grant the admin profile,
     * since it implies every present and future permission.
     */
    public function holdsPermissionsOf(Profile $profile): bool
    {
        if ($this->isAdministrator()) {
            return true;
        }

        if ($profile->isAdministrator()) {
            return false;
        }

        return $profile->permissionKeys()->diff($this->permissionKeys())->isEmpty();
    }
}
