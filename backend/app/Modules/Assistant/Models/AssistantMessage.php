<?php

namespace App\Modules\Assistant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantMessage extends Model
{
    protected $fillable = ['conversation_id', 'role', 'content', 'citations', 'structured_data'];

    protected function casts(): array
    {
        return ['citations' => 'array', 'structured_data' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'conversation_id');
    }
}
