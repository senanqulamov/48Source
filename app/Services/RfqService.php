<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\Rfq;
use App\Models\Sequence;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;

class RfqService
{
    /**
     * Create or update a draft.
     *
     * @param  array{title:string,description:?string,type:string,category_id:?int,currency:string,budget:?string,deadline_at:?string,delivery_location:?string,required_by:?string,requirements:?string,payment_terms:?string,scoring_preset:string,sealed:bool,items:array<int,array{name:string,quantity:string,unit:string,target_price:?string}>,suppliers:array<int,int>}  $data
     */
    public function save(User $buyer, array $data, ?Rfq $rfq = null): Rfq
    {
        if ($rfq && $rfq->status !== 'draft') {
            throw new BusinessRule('Only a draft request can be edited.');
        }

        return DB::transaction(function () use ($buyer, $data, $rfq) {
            $attrs = [
                'title' => trim($data['title']),
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? 'invited',
                'category_id' => $data['category_id'] ?: null,
                'currency' => $data['currency'] ?? 'USD',
                'budget_cents' => Money::parse($data['budget'] ?? null),
                'deadline_at' => $data['deadline_at'] ?: null,
                'delivery_location' => $data['delivery_location'] ?? null,
                'required_by' => $data['required_by'] ?: null,
                'requirements' => $data['requirements'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'scoring_preset' => $data['scoring_preset'] ?? 'balanced',
                'sealed' => (bool) ($data['sealed'] ?? false),
            ];
            $new = $rfq === null;
            $rfq ??= new Rfq(['number' => Sequence::next('RFQ'), 'buyer_id' => $buyer->id, 'status' => 'draft']);
            $rfq->fill($attrs)->save();

            $rfq->items()->delete();
            foreach (array_values($data['items'] ?? []) as $i => $row) {
                if (trim($row['name'] ?? '') === '') {
                    continue;
                }
                $rfq->items()->create([
                    'position' => $i + 1,
                    'name' => trim($row['name']),
                    'quantity_milli' => Money::parseQty($row['quantity'] ?? null) ?? 0,
                    'unit' => trim($row['unit'] ?? '') ?: 'pcs',
                    'target_price_cents' => Money::parse($row['target_price'] ?? null),
                ]);
            }

            $ids = Supplier::whereIn('id', $data['suppliers'] ?? [])->where('status', 'active')->pluck('id')->all();
            $rfq->invitations()->whereNotIn('supplier_id', $ids)->delete();
            foreach ($ids as $id) {
                $rfq->invitations()->firstOrCreate(['supplier_id' => $id]);
            }

            Audit::log('RFQ', $new ? 'Created draft request' : 'Edited draft request', $rfq);

            return $rfq->refresh();
        });
    }

    /** What still stops a draft from being published. Empty means ready. */
    public function publishBlockers(Rfq $rfq): array
    {
        $rfq->loadMissing('items', 'invitations.supplier');
        $out = [];
        if (trim((string) $rfq->title) === '') {
            $out[] = 'Give the request a title.';
        }
        if (! $rfq->deadline_at || $rfq->deadline_at->isPast()) {
            $out[] = 'Set a quotation deadline in the future.';
        }
        if ($rfq->items->isEmpty()) {
            $out[] = 'Add at least one item.';
        }
        if ($rfq->items->contains(fn ($i) => $i->quantity_milli <= 0)) {
            $out[] = 'Every item needs a quantity above zero.';
        }
        $active = $rfq->invitations->filter(fn ($i) => $i->supplier?->status === 'active')->count();
        if ($rfq->type === 'invited' && $active < 1) {
            $out[] = 'Invite at least one active supplier.';
        }

        return $out;
    }

    public function publish(Rfq $rfq): Rfq
    {
        if ($rfq->status !== 'draft') {
            throw new BusinessRule('Only a draft request can be published.');
        }
        if ($blockers = $this->publishBlockers($rfq)) {
            throw new BusinessRule($blockers[0]);
        }
        $rfq->update(['status' => 'open', 'published_at' => now()]);
        foreach ($rfq->invitations as $inv) {
            Notifier::supplier($inv->supplier_id, "New invitation to quote: {$rfq->number} {$rfq->title}", route('quotes.form', $rfq), 'Invitation');
        }
        Audit::log('RFQ', 'Published request to '.$rfq->invitations->count().' supplier(s)', $rfq);

        return $rfq;
    }

    public function close(Rfq $rfq): Rfq
    {
        if ($rfq->status !== 'open') {
            throw new BusinessRule('Only an open request can be closed to quotations.');
        }
        $rfq->update(['status' => 'closed', 'closed_at' => now()]);
        foreach ($rfq->liveQuotes()->pluck('supplier_id') as $sid) {
            Notifier::supplier($sid, "{$rfq->number} is closed. Your quotation is under review.", route('quotes.invitations'), 'RFQ');
        }
        Audit::log('RFQ', 'Closed request to quotations', $rfq);

        return $rfq;
    }

    public function cancel(Rfq $rfq, string $reason): Rfq
    {
        if (! in_array($rfq->status, ['draft', 'open', 'closed'], true)) {
            throw new BusinessRule('This request can no longer be cancelled.');
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw new BusinessRule('Give a short reason for cancelling.');
        }
        $wasLive = $rfq->status !== 'draft';
        $rfq->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => trim($reason)]);
        if ($wasLive) {
            foreach ($rfq->invitations as $inv) {
                Notifier::supplier($inv->supplier_id, "{$rfq->number} was cancelled by the buyer.", route('quotes.invitations'), 'RFQ');
            }
        }
        Audit::log('RFQ', 'Cancelled request: '.trim($reason), $rfq);

        return $rfq;
    }

    /** Close every open request whose deadline has passed. Run by the scheduler. */
    public function closeExpired(): int
    {
        $n = 0;
        foreach (Rfq::where('status', 'open')->where('deadline_at', '<=', now())->get() as $rfq) {
            $rfq->update(['status' => 'closed', 'closed_at' => now()]);
            Notifier::send($rfq->buyer, "{$rfq->number} reached its deadline with {$rfq->liveQuotes()->count()} quotation(s).", route('rfqs.show', $rfq), 'RFQ');
            Audit::log('RFQ', 'Closed automatically at the deadline', $rfq);
            $n++;
        }

        return $n;
    }
}
