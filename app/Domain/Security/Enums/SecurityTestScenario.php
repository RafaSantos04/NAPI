<?php

namespace App\Domain\Security\Enums;

/**
 * The two versions of the same operation a test runs side by side.
 */
enum SecurityTestScenario: string
{
    case Vulnerable = 'vulnerable';
    case Protected = 'protected';

    public function label(): string
    {
        return match ($this) {
            self::Vulnerable => 'Vulnerável',
            self::Protected => 'Protegido',
        };
    }
}
