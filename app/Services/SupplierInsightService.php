<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\Supplier;
use App\Models\SupplierAction;
use App\Models\SupplierDocument;
use App\Models\SupplierScorecard;
use App\Models\User;
use App\Support\Audit;
use App\Support\Notifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Supplier 360: documents, scorecards, action plans and a risk rating built from them. */
class SupplierInsightService
{
    /** @return array{level:string,points:int,reasons:array<int,string>} */
    public function risk(Supplier $s): array
    {
        $points = 0;
        $why = [];
        $add = function (int $p, string $text) use (&$points, &$why) {
            $points += $p;
            $why[] = $text;
        };
        if (in_array($s->status, ['blocked', 'inactive'], true)) {
            $add(50, 'The supplier is ' . mb_strtolower($s->statusLabel()) . '.');
        }
        $docs = $s->documents;
        $expired = $docs->filter->isExpired()->count();
        if ($expired) {
            $add(min(50, 25 * $expired), $expired . ' document' . ($expired === 1 ? ' has' : 's have') . ' expired.');
        }
        $soon = $docs->filter->expiresSoon()->count();
        if ($soon) {
            $add(10, $soon . ' document' . ($soon === 1 ? ' expires' : 's expire') . ' within 30 days.');
        }
        if (! $docs->first(fn ($d) => $d->type === 'registration' && $d->status === 'verified' && ! $d->isExpired())) {
            $add(15, 'No verified company registration on file.');
        }
        $card = $s->scorecards->sortByDesc('period')->first();
        if ($card && $card->score_tenths < 30) {
            $add(25, 'Latest scorecard is ' . number_format($card->score_tenths / 10, 1) . ' of 5.');
        } elseif ($card && $card->score_tenths < 35) {
            $add(10, 'Latest scorecard is ' . number_format($card->score_tenths / 10, 1) . ' of 5.');
        }
        $overdue = $s->actions->filter(fn ($a) => $a->status === 'open' && $a->due_on->isPast() && ! $a->due_on->isToday())->count();
        if ($overdue) {
            $add(15, $overdue . ' action' . ($overdue === 1 ? ' is' : 's are') . ' overdue.');
        }
        $late = $s->purchaseOrders()->whereIn('status', ['issued', 'acknowledged', 'in_transit'])->whereDate('expected_at', '<', now()->toDateString())->count();
        if ($late) {
            $add(15, $late . ' order' . ($late === 1 ? ' is' : 's are') . ' past the delivery date.');
        }

        return ['level' => $points >= 50 ? 'high' : ($points >= 20 ? 'medium' : 'low'), 'points' => $points, 'reasons' => $why];
    }

    public function addDocument(Supplier $s, User $user, array $data, ?UploadedFile $file = null): SupplierDocument
    {
        if (! isset(SupplierDocument::TYPES[$data['type'] ?? ''])) {
            throw new BusinessRule('Choose the kind of document.');
        }
        if (trim($data['title'] ?? '') === '') {
            throw new BusinessRule('Give the document a title.');
        }
        if ($user->role === 'supplier' && $user->supplier_id !== $s->id) {
            throw new BusinessRule('You can only add documents for your own company.');
        }
        $path = $file?->store("supplier-documents/{$s->id}", 'local');
        $doc = $s->documents()->create(['type' => $data['type'], 'title' => trim($data['title']), 'expires_on' => ($data['expires_on'] ?? null) ?: null, 'path' => $path, 'original_name' => $file?->getClientOriginalName(),
            'uploaded_by' => $user->id, 'status' => $user->isSourcing() ? 'verified' : 'pending', 'verified_by' => $user->isSourcing() ? $user->id : null]);
        if (! $user->isSourcing()) {
            Notifier::admins("{$s->name} added a document to check: {$doc->title}.", route('suppliers.show', $s), 'Supplier');
        }
        Audit::log('Supplier', "Added document \"{$doc->title}\"", $s, $s->name, supplierId: $s->id);

        return $doc->fresh();
    }

    public function verifyDocument(SupplierDocument $doc, User $user, bool $ok): SupplierDocument
    {
        $doc->update(['status' => $ok ? 'verified' : 'rejected', 'verified_by' => $user->id]);
        Notifier::supplier($doc->supplier_id, "Your document \"{$doc->title}\" was " . ($ok ? 'verified.' : 'not accepted. Please upload a correct one.'), route('suppliers.show', $doc->supplier_id), 'Supplier');
        Audit::log('Supplier', ($ok ? 'Verified' : 'Rejected') . " document \"{$doc->title}\"", $doc->supplier, $doc->supplier->name, supplierId: $doc->supplier_id);

        return $doc;
    }

    /** One scorecard per supplier and period (for example 2026-Q4). Marks are 1 to 5. */
    public function score(Supplier $s, User $user, string $period, array $marks, ?string $comment = null): SupplierScorecard
    {
        if (! preg_match('/^\d{4}-Q[1-4]$/', $period)) {
            throw new BusinessRule('The period looks like 2026-Q4.');
        }
        $vals = [];
        foreach (['quality', 'delivery', 'price', 'service'] as $k) {
            $v = (int) ($marks[$k] ?? 0);
            if ($v < 1 || $v > 5) {
                throw new BusinessRule('Give each of the four marks a value from 1 to 5.');
            }
            $vals[$k] = $v;
        }

        return DB::transaction(function () use ($s, $user, $period, $vals, $comment) {
            $card = SupplierScorecard::updateOrCreate(['supplier_id' => $s->id, 'period' => $period], $vals + ['score_tenths' => intdiv(array_sum($vals) * 10 + 2, 4), 'comment' => trim((string) $comment) ?: null, 'created_by' => $user->id]);
            $last = $s->scorecards()->orderByDesc('period')->limit(4)->pluck('score_tenths');
            $s->update(['rating_tenths' => intdiv((int) $last->sum() + intdiv($last->count(), 2), max(1, $last->count()))]);
            Audit::log('Supplier', "Scorecard {$period}: " . number_format($card->score_tenths / 10, 1) . ' of 5', $s, $s->name, supplierId: $s->id);

            return $card->fresh();
        });
    }

    public function addAction(Supplier $s, User $user, string $title, string $dueOn, ?string $detail = null): SupplierAction
    {
        if (trim($title) === '' || $dueOn === '') {
            throw new BusinessRule('An action needs a title and a due date.');
        }
        $a = $s->actions()->create(['title' => trim($title), 'detail' => trim((string) $detail) ?: null, 'due_on' => $dueOn, 'owner_id' => $user->id]);
        Notifier::supplier($s->id, "New action for your company: {$a->title}, due {$a->due_on->format('j M Y')}.", route('suppliers.show', $s), 'Supplier');
        Audit::log('Supplier', "Added action \"{$a->title}\"", $s, $s->name, supplierId: $s->id);

        return $a->fresh();
    }

    public function completeAction(SupplierAction $a): SupplierAction
    {
        if ($a->status !== 'open') {
            throw new BusinessRule('This action is already closed.');
        }
        $a->update(['status' => 'done', 'done_at' => now()]);
        Audit::log('Supplier', "Closed action \"{$a->title}\"", $a->supplier, $a->supplier->name, supplierId: $a->supplier_id);

        return $a;
    }
}
