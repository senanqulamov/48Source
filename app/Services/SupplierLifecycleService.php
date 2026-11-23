<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit;
use App\Support\Notifier;

class SupplierLifecycleService
{
    public function change(Supplier $supplier, string $to, User $by, ?string $reason = null): Supplier
    {
        if (! $by->allowed('suppliers', 'A')) {
            throw new BusinessRule('Your role cannot change a supplier\'s status.');
        }
        if (! in_array($to, Supplier::TRANSITIONS[$supplier->status] ?? [], true)) {
            throw new BusinessRule('A supplier cannot go from '.$supplier->statusLabel().' to '.(Supplier::STATUSES[$to] ?? $to).'.');
        }
        $reason = trim((string) $reason);
        if (in_array($to, ['inactive', 'blocked'], true) && mb_strlen($reason) < 5) {
            throw new BusinessRule('Give a reason. It is recorded in the supplier\'s history.');
        }
        $from = $supplier->statusLabel();
        $supplier->update([
            'status' => $to,
            'status_reason' => $reason ?: null,
            'approved_at' => $to === 'active' ? now() : $supplier->approved_at,
            'approved_by' => $to === 'active' ? $by->id : $supplier->approved_by,
        ]);
        Notifier::supplier($supplier, 'Your supplier account is now '.mb_strtolower($supplier->statusLabel()).($reason ? ": {$reason}" : '.'), route('dashboard'), 'Supplier');
        Audit::log('Supplier', "Status changed from {$from} to {$supplier->statusLabel()}".($reason ? ": {$reason}" : ''), $supplier, supplierId: $supplier->id);

        return $supplier;
    }
}
