<?php

namespace App\Domain\Tenancy\Enums;

enum OrganizationPermission: string
{
    case ViewOrganization = 'organizations.view';
    case UpdateOrganization = 'organizations.update';
    case ViewMembers = 'members.view';
    case ManageMembers = 'members.manage';
}
