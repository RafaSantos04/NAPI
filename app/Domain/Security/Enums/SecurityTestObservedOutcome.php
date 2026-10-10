<?php

namespace App\Domain\Security\Enums;

/**
 * What the system under test answered. There is no "error" case: when the
 * runner fails nothing was observed, and the column stays null.
 */
enum SecurityTestObservedOutcome: string
{
    case Allowed = 'allowed';
    case Denied = 'denied';

    public function label(): string
    {
        return match ($this) {
            self::Allowed => 'acesso permitido',
            self::Denied => 'acesso negado',
        };
    }
}
