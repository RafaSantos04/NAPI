<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\Menu;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MenuCreated implements Auditable
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
            'action' => 'menu_created',
            'subject_type' => Menu::class,
            'subject_id' => $this->menu->id,
        ];
    }
}
