<?php

namespace App\Domain\Tenancy\Enums;

enum OrganizationPermission: string
{
    case ViewOrganization = 'organizations.view';
    case UpdateOrganization = 'organizations.update';
    case ViewMembers = 'members.view';
    case ManageMembers = 'members.manage';

    // Project tree (P2, ADR-0012). `archive` reaches any record of the
    // organization; `archive_own` only the tree of a project the membership
    // created, a rule the Policies resolve. Both cover restoring.
    case ViewProjects = 'projects.view';
    case CreateProjects = 'projects.create';
    case UpdateProjects = 'projects.update';
    case ArchiveProjects = 'projects.archive';
    case ArchiveOwnProjects = 'projects.archive_own';

    case ViewApplications = 'applications.view';
    case CreateApplications = 'applications.create';
    case UpdateApplications = 'applications.update';
    case ArchiveApplications = 'applications.archive';
    case ArchiveOwnApplications = 'applications.archive_own';

    case ViewEnvironments = 'environments.view';
    case CreateEnvironments = 'environments.create';
    case UpdateEnvironments = 'environments.update';
    case ArchiveEnvironments = 'environments.archive';
    case ArchiveOwnEnvironments = 'environments.archive_own';

    // API specifications (P3, ADR-0014). `import` is apart from `update`
    // because importing is the expensive operation.
    case ViewApiSpecifications = 'api_specifications.view';
    case CreateApiSpecifications = 'api_specifications.create';
    case UpdateApiSpecifications = 'api_specifications.update';
    case ImportApiSpecifications = 'api_specifications.import';
    case ArchiveApiSpecifications = 'api_specifications.archive';
    case ArchiveOwnApiSpecifications = 'api_specifications.archive_own';
}
