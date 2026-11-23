<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\Contract;
use App\Models\ContractRelease;
use App\Models\Sequence;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Framework contracts with a price list, a value ceiling and release orders drawn against them. */
class ContractService
{
    public function save(User $user, array $data, array $items, ?Contract $contract = null): Contract
    {
        if ($contract && $contract->status !== 'draft') {
            throw new BusinessRule('Only a draft contract can be edited.');
        }
        $supplier = Supplier::find($data['supplier_id'] ?? null) ?? throw new BusinessRule('Choose a supplier.');
        if (! $supplier->isActive()) {
            throw new BusinessRule('Only an active supplier can hold a contract.');
        }
        if (trim($data['title'] ?? '') === '') {
            throw new BusinessRule('Give the contract a title.');
        }
        if (empty($data['starts_on']) || empty($data['ends_on']) || $data['ends_on'] < $data['starts_on']) {
            throw new BusinessRule('The end date must be on or after the start date.');
        }
        try {
            $ceiling = Money::parse($data['ceiling'] ?? null);
        } catch (InvalidArgumentException) {
            throw new BusinessRule('The value ceiling is not a valid amount.');
        }
        if ($ceiling !== null && $ceiling <= 0) {
            throw new BusinessRule('The value ceiling must be above zero, or left empty for no ceiling.');
        }
        $lines = [];
        foreach ($items as $i => $row) {
            $n = $i + 1;
            try {
                $price = Money::parse($row['price'] ?? null);
            } catch (InvalidArgumentException) {
                throw new BusinessRule("The price of item {$n} is not a valid amount.");
            }
            if (trim($row['name'] ?? '') === '' || $price === null || $price <= 0) {
                throw new BusinessRule("Item {$n} needs a name and a price above zero.");
            }
            $lines[] = ['name' => trim($row['name']), 'unit' => trim($row['unit'] ?? '') ?: 'pcs', 'unit_price_cents' => $price];
        }
        if (! $lines) {
            throw new BusinessRule('Add at least one priced item.');
        }

        return DB::transaction(function () use ($user, $data, $lines, $contract, $supplier, $ceiling) {
            $attrs = ['supplier_id' => $supplier->id, 'title' => trim($data['title']), 'currency' => $data['currency'] ?? 'USD', 'ceiling_cents' => $ceiling, 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'],
                'payment_terms' => trim($data['payment_terms'] ?? '') ?: null, 'terms' => trim($data['terms'] ?? '') ?: null];
            if ($contract) {
                $contract->update($attrs);
                $contract->items()->delete();
            } else {
                $contract = Contract::create($attrs + ['number' => Sequence::next('CT'), 'owner_id' => $user->id]);
                Audit::log('Contract', 'Created contract', $contract, $contract->number, supplierId: $supplier->id);
            }
            $contract->items()->createMany($lines);

            return $contract->fresh();
        });
    }

    public function activate(Contract $contract): Contract
    {
        if ($contract->status !== 'draft') {
            throw new BusinessRule('Only a draft contract can be activated.');
        }
        if ($contract->ends_on->isPast() && ! $contract->ends_on->isToday()) {
            throw new BusinessRule('The end date is already in the past.');
        }
        $contract->update(['status' => 'active']);
        Notifier::supplier($contract->supplier_id, "Contract {$contract->number} \"{$contract->title}\" is now active.", route('contracts.show', $contract), 'Contract');
        Audit::log('Contract', 'Activated contract', $contract, $contract->number, supplierId: $contract->supplier_id);

        return $contract;
    }

    public function terminate(Contract $contract, string $reason): Contract
    {
        if ($contract->status !== 'active') {
            throw new BusinessRule('Only an active contract can be terminated.');
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new BusinessRule('Give a reason of at least 10 characters.');
        }
        $contract->update(['status' => 'terminated', 'end_reason' => trim($reason)]);
        Notifier::supplier($contract->supplier_id, "Contract {$contract->number} was terminated.", route('contracts.show', $contract), 'Contract');
        Audit::log('Contract', 'Terminated contract', $contract, $contract->number, ['reason' => trim($reason)], $contract->supplier_id);

        return $contract;
    }

    /** @param array<int|string, string> $quantities contract item id => quantity typed by the user */
    public function release(Contract $contract, User $user, array $quantities, ?string $neededBy = null, ?string $note = null): ContractRelease
    {
        if ($contract->status !== 'active') {
            throw new BusinessRule('Release orders can only be placed on an active contract.');
        }
        if (now()->startOfDay()->lt($contract->starts_on) || now()->startOfDay()->gt($contract->ends_on)) {
            throw new BusinessRule('Today is outside the contract period.');
        }
        $lines = [];
        foreach ($contract->items as $item) {
            try {
                $qty = Money::parseQty($quantities[$item->id] ?? null);
            } catch (InvalidArgumentException) {
                throw new BusinessRule("The quantity for \"{$item->name}\" is not a number.");
            }
            if ($qty === null || $qty === 0) {
                continue;
            }
            if ($qty < 0) {
                throw new BusinessRule("The quantity for \"{$item->name}\" cannot be negative.");
            }
            $lines[] = ['contract_item_id' => $item->id, 'name' => $item->name, 'unit' => $item->unit, 'quantity_milli' => $qty, 'unit_price_cents' => $item->unit_price_cents, 'total_cents' => Money::lineTotal($item->unit_price_cents, $qty)];
        }
        if (! $lines) {
            throw new BusinessRule('Enter a quantity for at least one item.');
        }
        $total = array_sum(array_column($lines, 'total_cents'));

        return DB::transaction(function () use ($contract, $user, $lines, $total, $neededBy, $note) {
            $fresh = Contract::whereKey($contract->id)->lockForUpdate()->first();
            if ($fresh->ceiling_cents !== null && $fresh->released_cents + $total > $fresh->ceiling_cents) {
                throw new BusinessRule('This release of ' . Money::format($total, $fresh->currency) . ' goes over the contract ceiling. ' . Money::format($fresh->remainingCents(), $fresh->currency) . ' is left.');
            }
            $ro = ContractRelease::create(['number' => Sequence::next('RO'), 'contract_id' => $fresh->id, 'created_by' => $user->id, 'total_cents' => $total, 'needed_by' => $neededBy ?: null, 'note' => trim((string) $note) ?: null]);
            $ro->items()->createMany($lines);
            $fresh->increment('released_cents', $total);
            Notifier::supplier($fresh->supplier_id, "Release order {$ro->number} on contract {$fresh->number}: " . Money::format($total, $fresh->currency) . '.', route('contracts.show', $fresh), 'Contract');
            Audit::log('Contract', "Placed release order {$ro->number}", $fresh, $fresh->number, ['total_cents' => $total], $fresh->supplier_id);

            return $ro->fresh();
        });
    }

    /** Supplier accepts an issued release; the buyer side confirms delivery. A cancelled release gives its value back. */
    public function moveRelease(ContractRelease $ro, User $user, string $to): ContractRelease
    {
        $contract = $ro->contract;
        $ok = match ($to) {
            'accepted' => $ro->status === 'issued' && $user->role === 'supplier' && $user->supplier_id === $contract->supplier_id,
            'delivered' => $ro->status === 'accepted' && $user->isSourcing(),
            'cancelled' => $ro->status === 'issued' && $user->isSourcing(),
            default => false,
        };
        if (! $ok) {
            throw new BusinessRule('This release order cannot be moved to that step by you.');
        }

        return DB::transaction(function () use ($ro, $contract, $to) {
            $ro->update(['status' => $to] + ($to === 'accepted' ? ['accepted_at' => now()] : ($to === 'delivered' ? ['delivered_at' => now()] : [])));
            if ($to === 'cancelled') {
                $contract->decrement('released_cents', $ro->total_cents);
            }
            if ($to === 'accepted') {
                Notifier::send($contract->owner, "{$contract->supplier->name} accepted release order {$ro->number}.", route('contracts.show', $contract), 'Contract');
            } else {
                Notifier::supplier($contract->supplier_id, "Release order {$ro->number} is now " . mb_strtolower(ContractRelease::STATUSES[$to]) . '.', route('contracts.show', $contract), 'Contract');
            }
            Audit::log('Contract', "Release order {$ro->number} " . mb_strtolower(ContractRelease::STATUSES[$to]), $contract, $contract->number, supplierId: $contract->supplier_id);

            return $ro;
        });
    }

    /** Run by the scheduler: contracts past their end date stop accepting releases. */
    public function expireEnded(): int
    {
        $n = 0;
        foreach (Contract::where('status', 'active')->whereDate('ends_on', '<', now()->toDateString())->get() as $c) {
            $c->update(['status' => 'expired']);
            Notifier::send($c->owner, "Contract {$c->number} reached its end date and is now expired.", route('contracts.show', $c), 'Contract');
            Audit::log('Contract', 'Contract expired', $c, $c->number, supplierId: $c->supplier_id);
            $n++;
        }

        return $n;
    }

    /** Start a draft contract from a delivered order: same supplier, the order's items as the price list. */
    public function fromOrder(\App\Models\PurchaseOrder $po, User $user): Contract
    {
        if (! in_array($po->status, ['delivered', 'invoiced'], true)) {
            throw new BusinessRule('A contract can be started from an order once it is delivered.');
        }
        if (! $po->supplier->isActive()) {
            throw new BusinessRule('The supplier of this order is not active.');
        }

        return DB::transaction(function () use ($po, $user) {
            $c = Contract::create(['number' => Sequence::next('CT'), 'supplier_id' => $po->supplier_id, 'owner_id' => $user->id, 'rfq_id' => $po->rfq_id, 'purchase_order_id' => $po->id, 'title' => mb_strimwidth($po->rfq->title, 0, 220, '') . ' - framework',
                'currency' => $po->currency, 'starts_on' => now()->toDateString(), 'ends_on' => now()->addYear()->toDateString(), 'payment_terms' => $po->payment_terms]);
            foreach ($po->items as $it) {
                $c->items()->create(['name' => $it->name, 'unit' => $it->unit, 'unit_price_cents' => $it->unit_price_cents]);
            }
            Audit::log('Contract', "Created draft contract from order {$po->number}", $c, $c->number, supplierId: $po->supplier_id);
            Audit::log('Order', "Draft contract {$c->number} started from this order", $po, $po->number, supplierId: $po->supplier_id);

            return $c->fresh();
        });
    }
}
