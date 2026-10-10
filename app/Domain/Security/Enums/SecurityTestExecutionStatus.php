<?php

namespace App\Domain\Security\Enums;

/**
 * Whether the runner itself finished. Says nothing about security: a run
 * can complete and prove a vulnerability.
 */
enum SecurityTestExecutionStatus: string
{
    case Completed = 'completed';
    case Error = 'error';
}
