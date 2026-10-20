<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\Menu;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Menus are deleted for good, and the matrix rows of their key go with them
 * through the foreign key.
 */
class MenuDeleted implements Auditable
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Menu $menu,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'menu_deleted',
            'subject_type' => Menu::class,
            'subject_id' => $this->menu->id,
            // The row is gone, so the entry says which menu it was.
            'meta' => ['key' => $this->menu->key],
        ];
    }
}
