<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\Quote;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\Sequence;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Extra actions on a request: remind suppliers, duplicate, and the administrator-only corrections. */
class RfqToolsService
{
    public function __construct(private AuctionService $auctions) {}

    /** Remind invited suppliers that have not quoted. At most once every 12 hours per request. */
    public function remind(Rfq $rfq, User $by): int
    {
        if (! $rfq->isOpenForQuotes()) {
            throw new BusinessRule('Reminders can only be sent while the request is open.');
        }
        if ($rfq->last_reminded_at && $rfq->last_reminded_at->gt(now()->subHours(12))) {
            throw new BusinessRule('Suppliers were reminded ' . $rfq->last_reminded_at->diffForHumans() . '. Wait 12 hours between reminders.');
        }
        $quoted = $rfq->quotes()->where('status', 'submitted')->pluck('supplier_id');
        $ids = $rfq->invitations()->where('status', '!=', 'declined')->whereNotIn('supplier_id', $quoted)->pluck('supplier_id');
        if ($ids->isEmpty()) {
            throw new BusinessRule('Every invited supplier has already quoted or declined.');
        }
        foreach ($ids as $id) {
            Notifier::supplier($id, "Reminder from the buyer: {$rfq->number} \"{$rfq->title}\" closes {$rfq->deadline_at->format('j M Y, H:i')}.", route('quotes.form', $rfq), 'RFQ');
        }
        $rfq->update(['last_reminded_at' => now()]);
        Audit::log('RFQ', "Reminded {$ids->count()} supplier(s)", $rfq, $rfq->number);

        return $ids->count();
    }

    /** Remind one invited supplier that has not quoted. At most once every 12 hours per supplier and request. */
    public function remindOne(Rfq $rfq, User $by, int $supplierId): Supplier
    {
        if (! $rfq->isOpenForQuotes()) {
            throw new BusinessRule('Reminders can only be sent while the request is open.');
        }
        $inv = $rfq->invitations()->where('supplier_id', $supplierId)->with('supplier')->first();
        if (! $inv) {
            throw new BusinessRule('This supplier is not invited to the request.');
        }
        if ($inv->status === 'declined') {
            throw new BusinessRule('This supplier declined the invitation.');
        }
        if ($rfq->quotes()->where('supplier_id', $supplierId)->where('status', 'submitted')->exists()) {
            throw new BusinessRule('This supplier has already quoted.');
        }
        $key = "rfq-remind:{$rfq->id}:{$supplierId}";
        if (\Illuminate\Support\Facades\Cache::has($key)) {
            throw new BusinessRule('This supplier was reminded less than 12 hours ago.');
        }
        Notifier::supplier($supplierId, "Reminder from the buyer: {$rfq->number} \"{$rfq->title}\" closes {$rfq->deadline_at->format('j M Y, H:i')}.", route('quotes.form', $rfq), 'RFQ');
        \Illuminate\Support\Facades\Cache::put($key, true, now()->addHours(12));
        Audit::log('RFQ', "Reminded {$inv->supplier->name}", $rfq, $rfq->number);

        return $inv->supplier;
    }

    /** Add one more supplier to a draft or open request. On an open request the supplier is told at once. */
    public function invite(Rfq $rfq, User $by, int $supplierId): Supplier
    {
        if (! $by->isSourcing() || ! ($by->isAdmin() || $rfq->buyer_id === $by->id)) {
            throw new BusinessRule('Only the buyer of this request can invite suppliers.');
        }
        if (! in_array($rfq->status, ['draft', 'open'], true)) {
            throw new BusinessRule('Suppliers can be invited while the request is a draft or open.');
        }
        $supplier = Supplier::find($supplierId);
        if (! $supplier || $supplier->status !== 'active') {
            throw new BusinessRule('Choose an active supplier.');
        }
        if ($rfq->invitations()->where('supplier_id', $supplier->id)->exists()) {
            throw new BusinessRule("{$supplier->name} is already invited.");
        }
        $rfq->invitations()->create(['supplier_id' => $supplier->id]);
        if ($rfq->status === 'open') {
            Notifier::supplier($supplier->id, "New invitation to quote: {$rfq->number} {$rfq->title}", route('quotes.form', $rfq), 'Invitation');
        }
        Audit::log('RFQ', "Invited {$supplier->name}", $rfq, $rfq->number);

        return $supplier;
    }

    /** Administrator: switch between invited-only and public while nobody has been awarded. */
    public function setType(Rfq $rfq, User $admin, string $type): Rfq
    {
        if (! $admin->isAdmin()) {
            throw new BusinessRule('Only an administrator can change the request type.');
        }
        if (! isset(Rfq::TYPES[$type])) {
            throw new BusinessRule('Choose a request type.');
        }
        if (! in_array($rfq->status, ['draft', 'open'], true)) {
            throw new BusinessRule('The type can be changed while the request is a draft or open.');
        }
        if ($rfq->type !== $type) {
            $rfq->update(['type' => $type]);
            Audit::log('RFQ', 'Changed request type to '.Rfq::TYPES[$type], $rfq, $rfq->number);
        }

        return $rfq->fresh();
    }

    /** Administrator: mark that quotations on this request need an approved field assessment. */
    public function requireAssessment(Rfq $rfq, User $admin, bool $on): Rfq
    {
        if (! $admin->isAdmin()) {
            throw new BusinessRule('Only an administrator can change this.');
        }
        if (in_array($rfq->status, ['awarded', 'cancelled'], true)) {
            throw new BusinessRule('This request is finished.');
        }
        $rfq->update(['requires_assessment' => $on]);
        Audit::log('RFQ', $on ? 'Field assessment required' : 'Field assessment no longer required', $rfq, $rfq->number);

        return $rfq->fresh();
    }

    /** A new draft with the same details, items and invited suppliers. Deadline and dates are left empty. */
    public function duplicate(Rfq $rfq, User $by): Rfq
    {
        return DB::transaction(function () use ($rfq, $by) {
            $copy = Rfq::create(['number' => Sequence::next('RFQ'), 'title' => mb_strimwidth($rfq->title, 0, 240, '') . ' (copy)', 'description' => $rfq->description, 'type' => $rfq->type, 'category_id' => $rfq->category_id, 'buyer_id' => $by->id, 'status' => 'draft',
                'currency' => $rfq->currency, 'budget_cents' => $rfq->budget_cents, 'delivery_location' => $rfq->delivery_location, 'requirements' => $rfq->requirements, 'payment_terms' => $rfq->payment_terms, 'scoring_preset' => $rfq->scoring_preset, 'sealed' => $rfq->sealed]);
            foreach ($rfq->items as $i => $it) {
                $copy->items()->create(['position' => $i + 1, 'name' => $it->name, 'description' => $it->description, 'quantity_milli' => $it->quantity_milli, 'unit' => $it->unit, 'target_price_cents' => $it->target_price_cents]);
            }
            foreach ($rfq->invitations()->whereHas('supplier', fn ($s) => $s->where('status', 'active'))->pluck('supplier_id') as $sid) {
                $copy->invitations()->create(['supplier_id' => $sid]);
            }
            Audit::log('RFQ', "Created as a copy of {$rfq->number}", $copy, $copy->number);

            return $copy->fresh();
        });
    }

    /** Administrator: open a closed request again with a new deadline. */
    public function reopen(Rfq $rfq, User $admin, string $deadline, string $reason): Rfq
    {
        $this->adminOnly($admin);
        if ($rfq->status !== 'closed') {
            throw new BusinessRule('Only a closed request that is not awarded can be opened again.');
        }
        if ($rfq->openBafo() || $rfq->auction()->exists()) {
            throw new BusinessRule('This request already had a best-and-final round or an auction and cannot be opened again.');
        }
        $at = \Illuminate\Support\Carbon::parse($deadline ?: 'now');
        if ($at->isPast()) {
            throw new BusinessRule('The new deadline must be in the future.');
        }
        $this->reason($reason);
        $rfq->update(['status' => 'open', 'deadline_at' => $at, 'closed_at' => null]);
        foreach ($rfq->invitations()->where('status', '!=', 'declined')->pluck('supplier_id') as $sid) {
            Notifier::supplier($sid, "{$rfq->number} is open for quotations again until {$at->format('j M Y, H:i')}.", route('quotes.form', $rfq), 'RFQ');
        }
        Audit::log('RFQ', 'Administrator opened the request again', $rfq, $rfq->number, ['reason' => trim($reason)]);

        return $rfq;
    }

    /** Administrator: hand the request to another buyer. */
    public function reassign(Rfq $rfq, User $admin, int $buyerId): Rfq
    {
        $this->adminOnly($admin);
        $to = User::whereIn('role', ['admin', 'buyer'])->where('status', 'active')->find($buyerId) ?? throw new BusinessRule('Choose an active buyer.');
        if ($to->id === $rfq->buyer_id) {
            throw new BusinessRule("{$to->name} already owns this request.");
        }
        $rfq->update(['buyer_id' => $to->id]);
        Notifier::send($to, "Request {$rfq->number} was assigned to you.", route('rfqs.show', $rfq), 'RFQ');
        Audit::log('RFQ', "Administrator assigned the request to {$to->name}", $rfq, $rfq->number);

        return $rfq;
    }

    /**
     * Administrator: record a negotiated total on a quotation. The original total is kept, the reason is stored,
     * the supplier is told, and the line prices are reduced in proportion in whole cents.
     */
    public function adjustQuote(Quote $quote, User $admin, string $target, string $reason): Quote
    {
        $this->adminOnly($admin);
        $rfq = $quote->rfq;
        if ($rfq->status !== 'closed' || $quote->status !== 'submitted') {
            throw new BusinessRule('Prices can only be adjusted on a submitted quotation of a closed request that is not yet awarded.');
        }
        try {
            $new = Money::parse($target);
        } catch (InvalidArgumentException) {
            throw new BusinessRule('The target total is not a valid amount.');
        }
        if ($new === null || $new <= 0) {
            throw new BusinessRule('Enter the target total.');
        }
        if ($new === $quote->total_cents) {
            throw new BusinessRule('The target is the same as the current total.');
        }
        $floor = intdiv(($quote->original_total_cents ?? $quote->total_cents), 2);
        if ($new < $floor) {
            throw new BusinessRule('The target is less than half of the original quotation. Ask the supplier to send a revised quotation instead.');
        }
        $this->reason($reason);

        return DB::transaction(function () use ($quote, $admin, $new, $reason, $rfq) {
            $old = $quote->total_cents;
            $original = $quote->original_total_cents ?? $old;
            $this->auctions->rescale($quote, $new);
            $quote->update(['original_total_cents' => $original, 'adjust_reason' => trim($reason), 'adjusted_by' => $admin->id, 'adjusted_at' => now()]);
            Notifier::supplier($quote->supplier_id, "Your quotation on {$rfq->number} was adjusted from " . Money::format($old, $quote->currency) . ' to ' . Money::format($new, $quote->currency) . '. Reason: ' . trim($reason), route('quotes.form', $rfq), 'Quotation');
            Audit::log('Quotation', 'Administrator adjusted the quotation total', $rfq, $rfq->number, ['from_cents' => $old, 'to_cents' => $new, 'reason' => trim($reason)], $quote->supplier_id);

            return $quote->fresh();
        });
    }

    private function adminOnly(User $u): void
    {
        if (! $u->isAdmin()) {
            throw new BusinessRule('Only an administrator can do this.');
        }
    }

    private function reason(string $reason): void
    {
        if (mb_strlen(trim($reason)) < 10) {
            throw new BusinessRule('Give a reason of at least 10 characters. It is stored in the activity log.');
        }
    }
}
