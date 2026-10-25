<?php

namespace App\Domain\Tenancy\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Member = 'member';

    public function grants(OrganizationPermission $permission): bool
    {
        return match ($this) {
            self::Owner => true,
            self::Member => in_array($permission, [
                OrganizationPermission::ViewOrganization,
                OrganizationPermission::ViewMembers,
            ], true),
        };
    }
}
