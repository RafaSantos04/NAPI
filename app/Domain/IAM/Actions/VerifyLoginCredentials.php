<?php

namespace App\Domain\IAM\Actions;

use App\Domain\IAM\Exceptions\TooManyLoginAttemptsException;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use SensitiveParameter;

/**
 * The single place where a password is checked (ADR-0013). Every route that
 * takes a password calls it, so the admin form and the API login spend the
 * same budget: alternating between them gains nothing (FIND-033).
 *
 * Only the check is shared. Each adapter keeps its own mechanism: the admin
 * area opens a `web` session, the API issues a Sanctum token (ADR-0009).
 */
class VerifyLoginCredentials
{
    /**
     * Returns the user when the password is right and the account active,
     * null otherwise: an unknown e-mail, an inactive account and a wrong
     * password are indistinguishable to the caller (FIND-018).
     *
     * @throws TooManyLoginAttemptsException
     */
    public static function execute(string $email, #[SensitiveParameter] string $password, Request $request): ?User
    {
        // citext compares in lowercase, so the counters must too (FIND-027).
        // Hashed to keep raw e-mails out of the cache table and bound the key.
        $identity = hash('sha256', mb_strtolower($email));
        $ip = (string) $request->ip();
        $identityAndIp = "login:identity-ip:{$identity}|{$ip}";

        // [attempts, window in seconds]. One origin gets 10 tries an hour at
        // an account, half the identity quota: locking someone out takes
        // more than one IP (FIND-034).
        $counters = [
            $identityAndIp => [5, 30 * 60],
            "login:identity:{$identity}" => [20, 60 * 60],
            "login:ip:{$ip}" => [100, 60 * 60],
        ];

        // Reserve before the hash and decide on the value the increment
        // returns: it is atomic in the cache store, so simultaneous requests
        // each get their own count (FIND-028). Refused attempts count too.
        $retryAfter = null;

        foreach ($counters as $key => [$maxAttempts, $decaySeconds]) {
            if (RateLimiter::increment($key, $decaySeconds) > $maxAttempts) {
                $retryAfter = max($retryAfter ?? 1, RateLimiter::availableIn($key));
            }
        }

        if ($retryAfter !== null) {
            event(new Lockout($request));

            throw new TooManyLoginAttemptsException($retryAfter);
        }

        // validate() runs inside the guard's Timebox: a failure always takes
        // the same minimum time, whether or not the e-mail exists. It checks
        // without logging in, so the API never touches the session.
        /** @var SessionGuard $guard */
        $guard = Auth::guard('web');
        $credentials = ['email' => $email, 'password' => $password, 'is_active' => true];

        if (! $guard->validate($credentials)) {
            return null;
        }

        $user = $guard->getLastAttempted();

        if (! $user instanceof User) {
            return null;
        }

        // What attempt() would do: keep the hash at the current cost.
        if (config('hashing.rehash_on_login', true)) {
            $guard->getProvider()->rehashPasswordIfRequired($user, $credentials);
        }

        // Only the local-burst counter resets. Clearing the identity one would
        // hand a distributed attacker fresh quota at every login of the owner;
        // clearing the IP one would let a valid account wipe its own origin.
        RateLimiter::clear($identityAndIp);

        return $user;
    }
}
