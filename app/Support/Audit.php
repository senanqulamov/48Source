<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class Audit
{
    /** Record a business event. $supplierId links the event to a supplier's history. */
    public static function log(string $area, string $action, ?Model $subject = null, ?string $label = null, array $meta = [], ?int $supplierId = null): ActivityLog
    {
        $user = Auth::user();

        return ActivityLog::create([
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'System',
            'area' => $area,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => $label ?? ($subject->number ?? $subject->name ?? null),
            'supplier_id' => $supplierId ?? $user?->supplier_id,
            'meta' => $meta ?: null,
        ]);
    }
}
