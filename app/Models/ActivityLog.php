<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'actor_name', 'area', 'action', 'subject_type', 'subject_id', 'subject_label', 'supplier_id', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Link to the record the event is about, when the viewer may open it. */
    public function url(): ?string
    {
        return match ($this->subject_type) {
            'rfq' => $this->subject_label ? route('rfqs.show', $this->subject_label) : null,
            'purchase_order' => $this->subject_label ? route('orders.show', $this->subject_label) : null,
            default => null,
        };
    }
}
