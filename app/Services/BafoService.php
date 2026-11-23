<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\BafoRound;
use App\Models\Rfq;
use App\Models\User;
use App\Support\Audit;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;

/** Best-and-final rounds: after closing, the buyer lets the suppliers that quoted revise once more until a deadline. */
class BafoService
{
    public function open(Rfq $rfq, User $by, string $deadline, ?string $message = null): BafoRound
    {
        if ($rfq->status !== 'closed') {
            throw new BusinessRule('A best-and-final round can only be opened on a closed request that is not yet awarded.');
        }
        if ($rfq->openBafo()) {
            throw new BusinessRule('A round is already open.');
        }
        if ($rfq->auction()->where('status', 'live')->exists()) {
            throw new BusinessRule('An auction is running on this request.');
        }
        $at = \Illuminate\Support\Carbon::parse($deadline);
        if ($at->isPast()) {
            throw new BusinessRule('The deadline must be in the future.');
        }
        $suppliers = $rfq->quotes()->where('status', 'submitted')->pluck('supplier_id');
        if ($suppliers->isEmpty()) {
            throw new BusinessRule('No supplier has a quotation to improve.');
        }
        $round = BafoRound::create(['rfq_id' => $rfq->id, 'round' => (int) $rfq->bafoRounds()->max('round') + 1, 'opened_by' => $by->id, 'deadline_at' => $at, 'message' => trim((string) $message) ?: null]);
        foreach ($suppliers as $sid) {
            Notifier::supplier($sid, "Best and final offer requested on {$rfq->number}. You can revise your quotation until {$at->format('j M Y, H:i')}.", route('quotes.form', $rfq), 'Quotation');
        }
        Audit::log('Quotation', "Opened best-and-final round {$round->round} for {$suppliers->count()} supplier(s)", $rfq, $rfq->number);

        return $round->fresh();
    }

    public function close(BafoRound $round): BafoRound
    {
        if ($round->status !== 'open') {
            throw new BusinessRule('This round is already closed.');
        }
        $round->update(['status' => 'closed', 'closed_at' => now()]);
        Audit::log('Quotation', "Closed best-and-final round {$round->round}", $round->rfq, $round->rfq->number);

        return $round;
    }

    public function closeExpired(): int
    {
        $n = 0;
        foreach (BafoRound::where('status', 'open')->where('deadline_at', '<=', now())->with('rfq.buyer')->get() as $round) {
            $round->update(['status' => 'closed', 'closed_at' => now()]);
            Notifier::send($round->rfq->buyer, "Best-and-final round {$round->round} on {$round->rfq->number} ended. Compare the revised quotations.", route('rfqs.compare', $round->rfq), 'Quotation');
            $n++;
        }

        return $n;
    }
}
