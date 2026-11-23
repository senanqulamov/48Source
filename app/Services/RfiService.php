<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\Rfi;
use App\Models\Rfq;
use App\Models\Sequence;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;

/** Requests for information: questions sent to suppliers before a request for quotation exists. */
class RfiService
{
    public function save(User $user, array $data, array $questions, array $supplierIds, ?Rfi $rfi = null): Rfi
    {
        if ($rfi && $rfi->status !== 'draft') {
            throw new BusinessRule('Only a draft can be edited.');
        }
        if (trim($data['title'] ?? '') === '') {
            throw new BusinessRule('Give the information request a title.');
        }
        $questions = array_values(array_filter(array_map(fn ($q) => trim((string) $q), $questions), fn ($q) => $q !== ''));
        $ids = Supplier::whereIn('id', $supplierIds)->where('status', 'active')->pluck('id')->all();

        return DB::transaction(function () use ($user, $data, $questions, $ids, $rfi) {
            $attrs = ['title' => trim($data['title']), 'description' => trim($data['description'] ?? '') ?: null, 'questions' => $questions, 'deadline_at' => ($data['deadline_at'] ?? null) ?: null];
            if ($rfi) {
                $rfi->update($attrs);
            } else {
                $rfi = Rfi::create($attrs + ['number' => Sequence::next('RFI'), 'buyer_id' => $user->id]);
                Audit::log('RFI', 'Created information request', $rfi, $rfi->number);
            }
            $rfi->responses()->whereNotIn('supplier_id', $ids)->delete();
            foreach ($ids as $id) {
                $rfi->responses()->firstOrCreate(['supplier_id' => $id]);
            }

            return $rfi->fresh();
        });
    }

    public function publish(Rfi $rfi): Rfi
    {
        if ($rfi->status !== 'draft') {
            throw new BusinessRule('This information request was already sent.');
        }
        if (! $rfi->questions) {
            throw new BusinessRule('Add at least one question.');
        }
        if (! $rfi->responses()->exists()) {
            throw new BusinessRule('Choose at least one active supplier.');
        }
        if (! $rfi->deadline_at || $rfi->deadline_at->isPast()) {
            throw new BusinessRule('Set a deadline in the future.');
        }
        $rfi->update(['status' => 'open']);
        foreach ($rfi->responses as $r) {
            Notifier::supplier($r->supplier_id, "Information request {$rfi->number}: {$rfi->title}. Please answer by {$rfi->deadline_at->format('j M Y')}.", route('rfis.show', $rfi), 'RFI');
        }
        Audit::log('RFI', 'Sent information request to ' . $rfi->responses->count() . ' supplier(s)', $rfi, $rfi->number);

        return $rfi;
    }

    /** @param array<int, string> $answers question index => answer */
    public function answer(Rfi $rfi, User $user, array $answers): void
    {
        $resp = $rfi->responses()->where('supplier_id', $user->supplier_id)->first() ?? throw new BusinessRule('Your company was not asked.');
        if (! $rfi->isOpen()) {
            throw new BusinessRule('This information request is closed.');
        }
        if (! $user->supplier?->isActive()) {
            throw new BusinessRule('Your supplier account is not active.');
        }
        $clean = [];
        foreach ($rfi->questions as $i => $q) {
            $clean[$i] = trim((string) ($answers[$i] ?? ''));
            if ($clean[$i] === '') {
                throw new BusinessRule('Answer every question. Question ' . ($i + 1) . ' is empty.');
            }
        }
        $first = $resp->status !== 'answered';
        $resp->update(['status' => 'answered', 'answers' => $clean, 'answered_by' => $user->id, 'answered_at' => now()]);
        Notifier::send($rfi->buyer, "{$user->supplier->name} " . ($first ? 'answered' : 'changed its answers to') . " information request {$rfi->number}.", route('rfis.show', $rfi), 'RFI');
        Audit::log('RFI', $first ? 'Answered information request' : 'Changed answers', $rfi, $rfi->number, supplierId: $user->supplier_id);
    }

    public function close(Rfi $rfi): Rfi
    {
        if ($rfi->status !== 'open') {
            throw new BusinessRule('Only an open information request can be closed.');
        }
        $rfi->update(['status' => 'closed']);
        Audit::log('RFI', 'Closed information request', $rfi, $rfi->number);

        return $rfi;
    }

    public function closeExpired(): int
    {
        $n = 0;
        foreach (Rfi::where('status', 'open')->where('deadline_at', '<=', now())->get() as $rfi) {
            $rfi->update(['status' => 'closed']);
            Notifier::send($rfi->buyer, "Information request {$rfi->number} reached its deadline with " . $rfi->responses()->where('status', 'answered')->count() . ' answer(s).', route('rfis.show', $rfi), 'RFI');
            $n++;
        }

        return $n;
    }

    /** Start a draft request for quotation that invites the suppliers who answered. */
    public function toRfq(Rfi $rfi, User $user): Rfq
    {
        if ($rfi->status === 'draft') {
            throw new BusinessRule('Send the information request first.');
        }
        if ($rfi->rfq_id) {
            throw new BusinessRule('A request for quotation was already created from this information request.');
        }
        $ids = $rfi->responses()->where('status', 'answered')->pluck('supplier_id');
        if ($ids->isEmpty()) {
            throw new BusinessRule('No supplier has answered yet.');
        }

        return DB::transaction(function () use ($rfi, $user, $ids) {
            $rfq = Rfq::create(['number' => Sequence::next('RFQ'), 'title' => $rfi->title, 'description' => $rfi->description, 'type' => 'invited', 'buyer_id' => $user->id, 'status' => 'draft', 'currency' => 'USD']);
            foreach (Supplier::whereIn('id', $ids)->where('status', 'active')->pluck('id') as $sid) {
                $rfq->invitations()->create(['supplier_id' => $sid]);
            }
            $rfi->update(['rfq_id' => $rfq->id]);
            Audit::log('RFI', "Created draft request {$rfq->number} from the answers", $rfi, $rfi->number);
            Audit::log('RFQ', "Created draft request from information request {$rfi->number}", $rfq, $rfq->number);

            return $rfq->fresh();
        });
    }
}
