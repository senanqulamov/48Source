<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\Quote;
use App\Models\Rfq;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuoteService
{
    public const CURRENCIES = ['USD', 'EUR', 'AZN'];

    /** The supplier's invitation, creating one when it joins a public tender. */
    public function invitation(Rfq $rfq, Supplier $supplier, bool $join = false): ?RfqInvitation
    {
        $inv = $rfq->invitations()->where('supplier_id', $supplier->id)->first();
        if (! $inv && $join && $rfq->type === 'public' && $rfq->status === 'open' && $supplier->isActive()) {
            $inv = $rfq->invitations()->create(['supplier_id' => $supplier->id, 'status' => 'accepted', 'responded_at' => now()]);
            Notifier::send($rfq->buyer, "{$supplier->name} joined the public tender {$rfq->number}.", route('rfqs.show', $rfq), 'Invitation');
            Audit::log('Invitation', 'Joined public tender', $rfq, supplierId: $supplier->id);
        }

        return $inv;
    }

    public function respond(Rfq $rfq, Supplier $supplier, bool $accept, ?string $reason = null): RfqInvitation
    {
        $this->assertCanAct($rfq, $supplier);
        $inv = $this->invitation($rfq, $supplier) ?? throw new BusinessRule('Your company is not invited to this request.');
        if (! $accept && $rfq->quotes()->where('supplier_id', $supplier->id)->where('status', 'submitted')->exists()) {
            throw new BusinessRule('Withdraw your quotation before declining the invitation.');
        }
        $inv->update(['status' => $accept ? 'accepted' : 'declined', 'decline_reason' => $accept ? null : ($reason ? trim($reason) : null), 'responded_at' => now()]);
        Notifier::send($rfq->buyer, "{$supplier->name} ".($accept ? 'accepted' : 'declined')." the invitation to {$rfq->number}.", route('rfqs.show', $rfq), 'Invitation');
        Audit::log('Invitation', ($accept ? 'Accepted invitation' : 'Declined invitation').($reason ? ': '.trim($reason) : ''), $rfq, supplierId: $supplier->id);

        return $inv;
    }

    /**
     * Submit or revise a quotation. Leaving an item's price empty means "not quoted".
     *
     * @param  array{currency:string,delivery_days:int|string|null,payment_terms:?string,valid_until:?string,notes:?string,prices:array<int,string|null>}  $data
     */
    public function submit(Rfq $rfq, Supplier $supplier, User $user, array $data): Quote
    {
        $this->assertCanAct($rfq, $supplier);
        $inv = $this->invitation($rfq, $supplier, true) ?? throw new BusinessRule('Your company is not invited to this request.');
        if ($inv->status === 'declined') {
            throw new BusinessRule('You declined this invitation. Rejoin it before quoting.');
        }
        if (! in_array($data['currency'] ?? '', \App\Models\Currency::codes(), true)) {
            throw new BusinessRule('Choose a currency.');
        }
        $days = (int) ($data['delivery_days'] ?? 0);
        if ($days < 1 || $days > 365) {
            throw new BusinessRule('Enter the delivery time in days, between 1 and 365.');
        }
        if (empty($data['valid_until']) || now()->startOfDay()->gt($data['valid_until'])) {
            throw new BusinessRule('The quotation must be valid until today or later.');
        }

        $lines = [];
        foreach ($rfq->items as $item) {
            try {
                $price = Money::parse($data['prices'][$item->id] ?? null);
            } catch (InvalidArgumentException) {
                throw new BusinessRule("The price for \"{$item->name}\" is not a valid amount.");
            }
            if ($price !== null && $price <= 0) {
                throw new BusinessRule("The price for \"{$item->name}\" must be above zero.");
            }
            $lines[$item->id] = [$price, $price === null ? 0 : Money::lineTotal($price, $item->quantity_milli)];
        }
        if (! collect($lines)->contains(fn ($l) => $l[0] !== null)) {
            throw new BusinessRule('Enter a price for at least one item.');
        }

        return DB::transaction(function () use ($rfq, $supplier, $user, $data, $days, $lines, $inv) {
            $quote = Quote::firstOrNew(['rfq_id' => $rfq->id, 'supplier_id' => $supplier->id]);
            $revised = $quote->exists && $quote->status === 'submitted';
            $quote->fill([
                'submitted_by' => $user->id,
                'status' => 'submitted',
                'currency' => $data['currency'],
                'delivery_days' => $days,
                'payment_terms' => trim((string) ($data['payment_terms'] ?? '')) ?: null,
                'valid_until' => $data['valid_until'],
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'total_cents' => array_sum(array_column($lines, 1)),
                'revision' => ($quote->revision ?? 0) + 1,
                'submitted_at' => now(),
            ])->save();
            foreach ($lines as $itemId => [$price, $total]) {
                $quote->items()->updateOrCreate(['rfq_item_id' => $itemId], ['unit_price_cents' => $price, 'total_cents' => $total]);
            }
            if ($inv->status !== 'accepted') {
                $inv->update(['status' => 'accepted', 'responded_at' => now()]);
            }
            Notifier::send($rfq->buyer, "{$supplier->name} ".($revised ? 'revised its' : 'submitted a')." quotation on {$rfq->number}.", route('rfqs.show', $rfq), 'Quotation');
            Audit::log('Quotation', $revised ? 'Revised quotation' : 'Submitted quotation', $rfq, supplierId: $supplier->id);

            return $quote->refresh();
        });
    }

    public function withdraw(Quote $quote): Quote
    {
        $rfq = $quote->rfq;
        if ($quote->status !== 'submitted') {
            throw new BusinessRule('Only a submitted quotation can be withdrawn.');
        }
        if (! $rfq->isOpenForQuotes()) {
            throw new BusinessRule('The request is closed. The quotation can no longer be withdrawn.');
        }
        $quote->update(['status' => 'withdrawn']);
        Notifier::send($rfq->buyer, "{$quote->supplier->name} withdrew its quotation on {$rfq->number}.", route('rfqs.show', $rfq), 'Quotation');
        Audit::log('Quotation', 'Withdrew quotation', $rfq, supplierId: $quote->supplier_id);

        return $quote;
    }

    private function assertCanAct(Rfq $rfq, Supplier $supplier): void
    {
        if (! $supplier->isActive()) {
            throw new BusinessRule('Your supplier account is '.mb_strtolower($supplier->statusLabel()).'. You cannot respond to requests.');
        }
        // A best-and-final round lets suppliers that already quoted revise once more after closing.
        if ($rfq->status === 'closed' && $rfq->openBafo() && $rfq->quotes()->where('supplier_id', $supplier->id)->where('status', 'submitted')->exists()) {
            return;
        }
        if ($rfq->status !== 'open') {
            throw new BusinessRule('This request is not open for quotations.');
        }
        if (! $rfq->deadline_at || $rfq->deadline_at->isPast()) {
            throw new BusinessRule('The quotation deadline has passed.');
        }
    }
}
