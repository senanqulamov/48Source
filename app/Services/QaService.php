<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\Rfq;
use App\Models\RfqQuestion;
use App\Models\User;
use App\Support\Audit;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;

/** Questions suppliers ask about a request. Answers are shown to every invited supplier, without naming who asked. */
class QaService
{
    public function ask(Rfq $rfq, User $user, string $text): RfqQuestion
    {
        $supplier = $user->supplier;
        if ($user->role !== 'supplier' || ! $supplier?->isActive()) {
            throw new BusinessRule('Only an active supplier can ask a question.');
        }
        if (! $rfq->isOpenForQuotes()) {
            throw new BusinessRule('Questions can only be asked while the request is open.');
        }
        $inv = $rfq->invitations()->where('supplier_id', $supplier->id)->first();
        if (($rfq->type !== 'public' && ! $inv) || $inv?->status === 'declined') {
            throw new BusinessRule('Your company is not taking part in this request.');
        }
        if (mb_strlen(trim($text)) < 10) {
            throw new BusinessRule('Write the question in at least 10 characters.');
        }
        $q = $rfq->questions()->create(['supplier_id' => $supplier->id, 'asked_by' => $user->id, 'question' => trim($text)]);
        Notifier::send($rfq->buyer, "{$supplier->name} asked a question on {$rfq->number}.", route('rfqs.show', $rfq) . '?tab=qa', 'RFQ');
        Audit::log('RFQ', 'Supplier asked a question', $rfq, $rfq->number, supplierId: $supplier->id);

        return $q->fresh();
    }

    public function answer(RfqQuestion $q, User $user, string $text): RfqQuestion
    {
        $rfq = $q->rfq;
        if (! $user->isSourcing() || ! ($user->isAdmin() || $rfq->buyer_id === $user->id)) {
            throw new BusinessRule('Only the buyer of this request can answer.');
        }
        if (trim($text) === '') {
            throw new BusinessRule('Write an answer.');
        }
        $q->update(['answer' => trim($text), 'answered_by' => $user->id, 'answered_at' => now()]);
        foreach ($rfq->invitations()->where('status', '!=', 'declined')->pluck('supplier_id') as $sid) {
            Notifier::supplier($sid, "A question on {$rfq->number} was answered. The answer is visible to every invited supplier.", route('quotes.form', $rfq), 'RFQ');
        }
        Audit::log('RFQ', 'Answered a supplier question', $rfq, $rfq->number);

        return $q;
    }
}
