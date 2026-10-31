<?php

namespace App\Modules\Admin\Models;

use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminReport extends Model
{
    protected $fillable = ['target_type', 'target_id', 'reporter_id', 'reason', 'details', 'status', 'resolved_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reporter_id'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class, 'target_id'); }
    public function reportedUser(): BelongsTo { return $this->belongsTo(User::class, 'target_id'); }
}
