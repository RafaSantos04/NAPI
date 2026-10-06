<?php

namespace App\Http\Admin;

use App\Models\User;

/**
 * Sections of the admin area and the Policy ability that shows each one.
 *
 * Also the rule for entering the area at all: a user gets in when at least
 * one section is visible, i.e. when they hold a functional permission the
 * area can actually use. No dedicated "admin" flag or permission key; the
 * answer comes from the Policies, which ask User::hasPermission() (ADR-0008).
 *
 * Showing a link is not authorization: every section's routes keep their own
 * `permission:` middleware and Policy checks.
 */
final class AdminNavigation
{
    /**
     * @return list<array{label: string, route: string, active: string, ability: string, subject: class-string}>
     */
    private static function sections(): array
    {
        return [
            [
                'label' => 'Usuários',
                'route' => 'admin.users.index',
                'active' => 'admin.users.*',
                'ability' => 'viewAny',
                'subject' => User::class,
            ],
        ];
    }

    /**
     * @return list<array{label: string, route: string, active: string}>
     */
    public static function for(User $user): array
    {
        $visible = array_filter(
            self::sections(),
            fn (array $section) => $user->can($section['ability'], $section['subject']),
        );

        return array_values(array_map(
            fn (array $section) => [
                'label' => $section['label'],
                'route' => $section['route'],
                'active' => $section['active'],
            ],
            $visible,
        ));
    }

    public static function allows(User $user): bool
    {
        return self::for($user) !== [];
    }
}
