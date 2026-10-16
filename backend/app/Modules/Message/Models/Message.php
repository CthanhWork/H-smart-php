<?php

namespace App\Modules\Message\Models;

use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['product_id', 'sender_id', 'recipient_id', 'body', 'read_at'];

    protected function casts(): array { return ['read_at' => 'datetime']; }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
    public function recipient(): BelongsTo { return $this->belongsTo(User::class, 'recipient_id'); }
}
