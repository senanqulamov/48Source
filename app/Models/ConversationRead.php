<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversationRead extends Model
{
    public $timestamps = false;

    protected $fillable = ['conversation_id', 'user_id', 'message_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'message_id' => 'integer'];
    }
}
