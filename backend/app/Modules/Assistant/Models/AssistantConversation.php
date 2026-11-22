<?php

namespace App\Modules\Assistant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistantConversation extends Model
{
    protected $fillable = ['user_id', 'title', 'search_context'];

    protected function casts(): array
    {
        return ['search_context' => 'array'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class, 'conversation_id');
    }
}
