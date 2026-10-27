<?php

use App\Domain\Tenancy\Enums\OrganizationPermission;
use App\Domain\Tenancy\Enums\OrganizationRole;

// Expected grants per permission, written out by hand: [owner, manager, member].
dataset('organization permission matrix', [
    'organizations.view' => [OrganizationPermission::ViewOrganization, true, true, true],
    'organizations.update' => [OrganizationPermission::UpdateOrganization, true, false, false],
    'members.view' => [OrganizationPermission::ViewMembers, true, true, true],
    'members.manage' => [OrganizationPermission::ManageMembers, true, false, false],
    'projects.view' => [OrganizationPermission::ViewProjects, true, true, true],
    'projects.create' => [OrganizationPermission::CreateProjects, true, true, true],
    'projects.update' => [OrganizationPermission::UpdateProjects, true, true, true],
    'projects.archive' => [OrganizationPermission::ArchiveProjects, true, true, false],
    'projects.archive_own' => [OrganizationPermission::ArchiveOwnProjects, true, true, true],
    'applications.view' => [OrganizationPermission::ViewApplications, true, true, true],
    'applications.create' => [OrganizationPermission::CreateApplications, true, true, true],
    'applications.update' => [OrganizationPermission::UpdateApplications, true, true, true],
    'applications.archive' => [OrganizationPermission::ArchiveApplications, true, true, false],
    'applications.archive_own' => [OrganizationPermission::ArchiveOwnApplications, true, true, true],
    'environments.view' => [OrganizationPermission::ViewEnvironments, true, true, true],
    'environments.create' => [OrganizationPermission::CreateEnvironments, true, true, true],
    'environments.update' => [OrganizationPermission::UpdateEnvironments, true, true, true],
    'environments.archive' => [OrganizationPermission::ArchiveEnvironments, true, true, false],
    'environments.archive_own' => [OrganizationPermission::ArchiveOwnEnvironments, true, true, true],
]);

test('each role grants exactly its share of the permission catalog', function (
    OrganizationPermission $permission,
    bool $owner,
    bool $manager,
    bool $member,
) {
    expect(OrganizationRole::Owner->grants($permission))->toBe($owner)
        ->and(OrganizationRole::Manager->grants($permission))->toBe($manager)
        ->and(OrganizationRole::Member->grants($permission))->toBe($member);
})->with('organization permission matrix');

test('the matrix covers every permission in the catalog', function () {
    // A new permission must be placed in the matrix above, not inherit a
    // grant silently from the exclusion lists of the roles.
    expect(array_map(fn (OrganizationPermission $permission) => $permission->value, OrganizationPermission::cases()))
        ->toBe([
            'organizations.view', 'organizations.update', 'members.view', 'members.manage',
            'projects.view', 'projects.create', 'projects.update', 'projects.archive', 'projects.archive_own',
            'applications.view', 'applications.create', 'applications.update', 'applications.archive', 'applications.archive_own',
            'environments.view', 'environments.create', 'environments.update', 'environments.archive', 'environments.archive_own',
        ]);
});
