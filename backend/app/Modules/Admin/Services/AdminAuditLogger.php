<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Models\AdminAuditLog;
use App\Modules\User\Models\User;

class AdminAuditLogger
{
    public function record(?User $admin, string $action, string $subjectType, ?int $subjectId, string $summary, array $details = []): void
    {
        AdminAuditLog::create([
            'admin_id' => $admin?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'summary' => $summary,
            'details' => $details ?: null,
        ]);
    }
}
