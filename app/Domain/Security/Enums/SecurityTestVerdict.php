<?php

namespace App\Domain\Security\Enums;

/**
 * What a run means for security. Exposed: unauthorized access happened.
 * Protected: it did not. Inconclusive: the run failed for a technical
 * reason, which is never evidence of protection.
 */
enum SecurityTestVerdict: string
{
    case Exposed = 'exposed';
    case Protected = 'protected';
    case Inconclusive = 'inconclusive';

    public function label(): string
    {
        return strtoupper($this->value);
    }
}
