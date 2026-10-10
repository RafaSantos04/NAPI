<?php

namespace App\Domain\Security\Enums;

/**
 * Stable identity of a Security Lab test, persisted in
 * security_test_runs.test_key. The label is presentation and may change;
 * the value may not (same lesson as menus.key, ADR-0008).
 */
enum SecurityTest: string
{
    case Idor = 'idor';
    case MassAssignment = 'mass_assignment';

    public function label(): string
    {
        return match ($this) {
            self::Idor => 'IDOR / BOLA',
            self::MassAssignment => 'Mass Assignment',
        };
    }
}
