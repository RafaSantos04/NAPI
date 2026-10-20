<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\Menu;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The key never changes, so a menu update is a navigation change: label,
 * route, position, status. Permissions are not affected (ADR-0008).
 */
class MenuUpdated implements Auditable
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, string>  $fields  Names of the changed attributes.
     */
    public function __construct(
        public Menu $menu,
        public array $fields,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'menu_updated',
            'subject_type' => Menu::class,
            'subject_id' => $this->menu->id,
            'meta' => ['fields' => $this->fields],
        ];
    }
}
