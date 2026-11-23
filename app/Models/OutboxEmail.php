<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutboxEmail extends Model
{
    protected $fillable = ['user_id', 'to', 'subject', 'body', 'url', 'area', 'status', 'error', 'sent_at'];
    protected $attributes = ['status' => 'queued', 'error' => null, 'sent_at' => null, 'url' => null];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
