<?php

namespace App\Domain\Tenancy\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Member = 'member';

    public function grants(OrganizationPermission $permission): bool
    {
        return match ($this) {
            self::Owner => true,
            // Runs the whole project tree, but neither the organization nor
            // its roster: promoting someone never hands over the tenant.
            self::Manager => ! in_array($permission, [
                OrganizationPermission::UpdateOrganization,
                OrganizationPermission::ManageMembers,
            ], true),
            // Works on the project tree; archiving is limited to what the
            // member created (`archive_own`, checked by the Policies).
            self::Member => ! in_array($permission, [
                OrganizationPermission::UpdateOrganization,
                OrganizationPermission::ManageMembers,
                OrganizationPermission::ArchiveProjects,
                OrganizationPermission::ArchiveApplications,
                OrganizationPermission::ArchiveEnvironments,
            ], true),
        };
    }
}
