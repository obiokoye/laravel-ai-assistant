<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $guarded = [];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
