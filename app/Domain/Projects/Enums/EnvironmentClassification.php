<?php

namespace App\Domain\Projects\Enums;

/**
 * Closed on purpose: later phases attach rules to the classification (e.g.
 * production requiring stronger authorization in P10). Free naming stays in
 * the environment's name and slug (ADR-0012).
 */
enum EnvironmentClassification: string
{
    case Production = 'production';
    case Staging = 'staging';
    case Development = 'development';
    case Other = 'other';
}
