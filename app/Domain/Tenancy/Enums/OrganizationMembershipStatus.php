<?php

namespace App\Domain\Tenancy\Enums;

enum OrganizationMembershipStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
