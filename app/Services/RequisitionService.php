<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\Requisition;
use App\Models\Rfq;
use App\Models\Sequence;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Internal purchase requests: draft, approval chain, conversion into a request for quotation. */
class RequisitionService
{
    /** @param array<int, array{name:string,quantity:string,unit:string,est_price?:string}> $items */
    public function save(User $user, array $data, array $items, ?Requisition $req = null): Requisition
    {
        if ($req && $req->status !== 'draft') {
            throw new BusinessRule('Only a draft requisition can be edited.');
        }
        if ($req && $req->requester_id !== $user->id && ! $user->isAdmin()) {
            throw new BusinessRule('Only the requester can edit this requisition.');
        }
        if (trim($data['title'] ?? '') === '') {
            throw new BusinessRule('Give the requisition a title.');
        }
        $lines = [];
        foreach ($items as $i => $row) {
            $n = $i + 1;
            if (trim($row['name'] ?? '') === '') {
                throw new BusinessRule("Item {$n} needs a name.");
            }
            try {
                $qty = Money::parseQty($row['quantity'] ?? null);
                $price = Money::parse($row['est_price'] ?? null);
            } catch (InvalidArgumentException) {
                throw new BusinessRule("Item {$n} has a quantity or price that is not a number.");
            }
            if (! $qty || $qty <= 0) {
                throw new BusinessRule("Item {$n} needs a quantity above zero.");
            }
            if ($price !== null && $price < 0) {
                throw new BusinessRule("Item {$n} cannot have a negative price.");
            }
            $lines[] = ['name' => trim($row['name']), 'quantity_milli' => $qty, 'unit' => trim($row['unit'] ?? '') ?: 'pcs', 'est_price_cents' => $price, 'total_cents' => $price === null ? 0 : Money::lineTotal($price, $qty)];
        }
        if (! $lines) {
            throw new BusinessRule('Add at least one item.');
        }

        return DB::transaction(function () use ($user, $data, $lines, $req) {
            $attrs = ['title' => trim($data['title']), 'justification' => trim($data['justification'] ?? '') ?: null, 'needed_by' => ($data['needed_by'] ?? null) ?: null,
                'category_id' => ($data['category_id'] ?? null) ?: null, 'currency' => $data['currency'] ?? 'USD', 'total_cents' => array_sum(array_column($lines, 'total_cents'))];
            if ($req) {
                $req->update($attrs);
                $req->items()->delete();
            } else {
                $req = Requisition::create($attrs + ['number' => Sequence::next('PR'), 'requester_id' => $user->id]);
                Audit::log('Requisition', 'Created requisition', $req, $req->number);
            }
            $req->items()->createMany($lines);

            return $req->fresh();
        });
    }

    /** The approval levels this amount has to pass. */
    public function chainFor(int $totalCents): array
    {
        $levels = config('procurement.approval_levels');

        return array_values(array_filter($levels, fn ($l, $i) => $i === 0 || ($totalCents > 0 && $totalCents >= $l['min_cents']), ARRAY_FILTER_USE_BOTH));
    }

    public function submit(Requisition $req, User $user): Requisition
    {
        if ($req->status !== 'draft') {
            throw new BusinessRule('This requisition was already submitted.');
        }
        if ($req->requester_id !== $user->id && ! $user->isAdmin()) {
            throw new BusinessRule('Only the requester can submit this requisition.');
        }
        if (! $req->items()->exists()) {
            throw new BusinessRule('Add at least one item before submitting.');
        }

        return DB::transaction(function () use ($req) {
            $req->approvals()->delete();
            foreach ($this->chainFor($req->total_cents) as $i => $level) {
                $req->approvals()->create(['step' => $i + 1, 'role' => $level['role'], 'label' => $level['label'], 'status' => $i === 0 ? 'pending' : 'waiting']);
            }
            $req->update(['status' => 'pending', 'submitted_at' => now(), 'decided_at' => null]);
            $this->notifyApprovers($req);
            Audit::log('Requisition', 'Submitted for approval', $req, $req->number);

            return $req->fresh();
        });
    }

    public function canDecide(Requisition $req, User $user): bool
    {
        $step = $req->status === 'pending' ? $req->approvals()->where('status', 'pending')->orderBy('step')->first() : null;

        return $step !== null && $user->allowed('requisitions', 'A') && $req->requester_id !== $user->id && ($user->isAdmin() || $user->role === $step->role);
    }

    public function decide(Requisition $req, User $user, bool $approve, ?string $comment = null): Requisition
    {
        if ($req->status !== 'pending') {
            throw new BusinessRule('This requisition is not waiting for approval.');
        }
        if ($req->requester_id === $user->id) {
            throw new BusinessRule('You cannot approve your own requisition. Another approver has to decide.');
        }
        if (! $this->canDecide($req, $user)) {
            throw new BusinessRule('This step is waiting for another approver.');
        }
        $comment = trim((string) $comment);
        if (! $approve && mb_strlen($comment) < 5) {
            throw new BusinessRule('Say why the requisition is rejected, in at least 5 characters.');
        }

        return DB::transaction(function () use ($req, $user, $approve, $comment) {
            $step = $req->approvals()->where('status', 'pending')->orderBy('step')->firstOrFail();
            $step->update(['status' => $approve ? 'approved' : 'rejected', 'approver_id' => $user->id, 'comment' => $comment ?: null, 'decided_at' => now()]);
            $next = $approve ? $req->approvals()->where('status', 'waiting')->orderBy('step')->first() : null;
            if (! $approve) {
                $req->update(['status' => 'rejected', 'decided_at' => now()]);
                Notifier::send($req->requester, "Requisition {$req->number} was rejected: {$comment}", route('requisitions.show', $req), 'Requisition');
            } elseif ($next) {
                $next->update(['status' => 'pending']);
                $this->notifyApprovers($req);
            } else {
                $req->update(['status' => 'approved', 'decided_at' => now()]);
                Notifier::send($req->requester, "Requisition {$req->number} is approved. It can now become a request for quotation.", route('requisitions.show', $req), 'Requisition');
            }
            Audit::log('Requisition', ($approve ? 'Approved' : 'Rejected') . " step {$step->step}: {$step->label}", $req, $req->number);

            return $req->fresh();
        });
    }

    /** Turn an approved requisition into a draft request for quotation with the same items. */
    public function convert(Requisition $req, User $user): Rfq
    {
        if ($req->status !== 'approved') {
            throw new BusinessRule('Only an approved requisition can become a request for quotation.');
        }

        return DB::transaction(function () use ($req, $user) {
            $rfq = Rfq::create(['number' => Sequence::next('RFQ'), 'title' => $req->title, 'description' => $req->justification, 'type' => 'invited', 'category_id' => $req->category_id, 'buyer_id' => $user->id,
                'status' => 'draft', 'currency' => $req->currency, 'budget_cents' => $req->total_cents ?: null, 'required_by' => $req->needed_by]);
            foreach ($req->items as $i => $it) {
                $rfq->items()->create(['position' => $i + 1, 'name' => $it->name, 'quantity_milli' => $it->quantity_milli, 'unit' => $it->unit, 'target_price_cents' => $it->est_price_cents]);
            }
            $req->update(['status' => 'converted', 'rfq_id' => $rfq->id]);
            Notifier::send($req->requester, "Requisition {$req->number} became request {$rfq->number}.", route('requisitions.show', $req), 'Requisition');
            Audit::log('Requisition', "Converted to {$rfq->number}", $req, $req->number);
            Audit::log('RFQ', "Created draft request from requisition {$req->number}", $rfq, $rfq->number);

            return $rfq->fresh();
        });
    }

    public function cancel(Requisition $req, User $user): Requisition
    {
        if (! in_array($req->status, ['draft', 'pending'], true)) {
            throw new BusinessRule('Only a draft or pending requisition can be cancelled.');
        }
        if ($req->requester_id !== $user->id && ! $user->isAdmin()) {
            throw new BusinessRule('Only the requester can cancel this requisition.');
        }
        $req->update(['status' => 'cancelled', 'decided_at' => now()]);
        Audit::log('Requisition', 'Cancelled requisition', $req, $req->number);

        return $req;
    }

    private function notifyApprovers(Requisition $req): void
    {
        $step = $req->approvals()->where('status', 'pending')->orderBy('step')->first();
        if (! $step) {
            return;
        }
        $who = User::where('status', 'active')->whereIn('role', array_unique([$step->role, 'admin']))->where('id', '!=', $req->requester_id)->get()->filter(fn (User $u) => $u->allowed('requisitions', 'A'));
        Notifier::send($who, "Requisition {$req->number} is waiting for your approval ({$step->label}).", route('requisitions.show', $req), 'Requisition');
    }
}
