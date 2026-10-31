<?php

namespace App\Modules\Admin\Models;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    protected $fillable = ['admin_id', 'action', 'subject_type', 'subject_id', 'summary', 'details'];
    protected function casts(): array { return ['details' => 'array']; }
    public function admin(): BelongsTo { return $this->belongsTo(User::class, 'admin_id'); }
}
