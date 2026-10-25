<?php

namespace App\Domain\Tenancy;

use App\Models\Organization;
use LogicException;

class OrganizationContext
{
    private ?Organization $organization = null;

    public function set(Organization $organization): void
    {
        if ($this->organization !== null && ! $this->organization->is($organization)) {
            throw new LogicException('The organization context is already set for this request.');
        }

        $this->organization = $organization;
    }

    public function get(): ?Organization
    {
        return $this->organization;
    }

    public function clear(): void
    {
        $this->organization = null;
    }
}
