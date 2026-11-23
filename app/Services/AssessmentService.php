<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\FieldAssessment;
use App\Models\Rfq;
use App\Models\Sequence;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;

/** Field assessments: the buyer asks, the supplier's field worker answers a checklist, the buyer approves or returns it. */
class AssessmentService
{
    public function request(User $by, Supplier $supplier, string $title, array $checklist, ?Rfq $rfq = null, ?int $assigneeId = null, ?string $dueOn = null): FieldAssessment
    {
        if (! $supplier->isActive()) {
            throw new BusinessRule('Only an active supplier can be asked for a field assessment.');
        }
        $checklist = array_values(array_filter(array_map(fn ($c) => trim((string) $c), $checklist), fn ($c) => $c !== ''));
        if (trim($title) === '' || ! $checklist) {
            throw new BusinessRule('A field assessment needs a title and at least one checklist point.');
        }
        $assignee = $assigneeId ? User::find($assigneeId) : null;
        if ($assignee && ($assignee->supplier_id !== $supplier->id || ! in_array($assignee->role, ['supplier', 'supplier_worker'], true))) {
            throw new BusinessRule('The assessment can only be assigned to a user of that supplier.');
        }
        $fa = FieldAssessment::create(['number' => Sequence::next('FA'), 'supplier_id' => $supplier->id, 'rfq_id' => $rfq?->id, 'requested_by' => $by->id, 'assigned_to' => $assignee?->id, 'title' => trim($title), 'checklist' => $checklist, 'due_on' => $dueOn ?: null]);
        $who = $assignee ? [$assignee] : User::where('supplier_id', $supplier->id)->where('status', 'active')->get();
        Notifier::send($who, "Field assessment {$fa->number} requested: {$fa->title}.", route('assessments.show', $fa), 'Field');
        Audit::log('Field', "Requested field assessment {$fa->number}", $rfq, $rfq?->number ?? $fa->number, supplierId: $supplier->id);

        return $fa->fresh();
    }

    private function assertWorker(FieldAssessment $fa, User $user): void
    {
        if ($user->supplier_id !== $fa->supplier_id || ! in_array($user->role, ['supplier', 'supplier_worker'], true)) {
            throw new BusinessRule('Only the supplier asked for this assessment can fill it in.');
        }
        if ($fa->assigned_to && $fa->assigned_to !== $user->id && $user->role !== 'supplier') {
            throw new BusinessRule('This assessment is assigned to a colleague.');
        }
        if (! in_array($fa->status, ['assigned', 'in_progress', 'rejected'], true)) {
            throw new BusinessRule('This assessment was already submitted.');
        }
    }

    /** @param array<int, array{ok?:string|bool|null,note?:string}> $answers checklist index => answer */
    public function save(FieldAssessment $fa, User $user, array $answers, ?string $findings): FieldAssessment
    {
        $this->assertWorker($fa, $user);
        $clean = [];
        foreach ($fa->checklist as $i => $_) {
            $a = $answers[$i] ?? [];
            $ok = $a['ok'] ?? null;
            $clean[$i] = ['ok' => $ok === null || $ok === '' ? null : in_array($ok, [true, 1, '1', 'yes'], true), 'note' => trim((string) ($a['note'] ?? ''))];
        }
        $fa->update(['answers' => $clean, 'findings' => trim((string) $findings) ?: null, 'status' => 'in_progress', 'assigned_to' => $fa->assigned_to ?? $user->id]);

        return $fa;
    }

    public function submit(FieldAssessment $fa, User $user): FieldAssessment
    {
        $this->assertWorker($fa, $user);
        foreach ($fa->checklist as $i => $point) {
            if (($fa->answers[$i]['ok'] ?? null) === null) {
                throw new BusinessRule('Answer every checklist point with yes or no before submitting.');
            }
            if ($fa->answers[$i]['ok'] === false && ($fa->answers[$i]['note'] ?? '') === '') {
                throw new BusinessRule("Add a note to explain the \"no\" on: {$point}");
            }
        }
        if (mb_strlen((string) $fa->findings) < 10) {
            throw new BusinessRule('Write the findings in at least 10 characters.');
        }
        $fa->update(['status' => 'submitted', 'submitted_at' => now(), 'decided_by' => null, 'decided_at' => null, 'decision_note' => null]);
        Notifier::send($fa->requester, "Field assessment {$fa->number} was submitted by {$fa->supplier->name}.", route('assessments.show', $fa), 'Field');
        Audit::log('Field', "Submitted field assessment {$fa->number}", $fa->rfq, $fa->rfq?->number ?? $fa->number, supplierId: $fa->supplier_id);

        return $fa;
    }

    public function decide(FieldAssessment $fa, User $user, bool $approve, ?string $note = null): FieldAssessment
    {
        if ($fa->status !== 'submitted') {
            throw new BusinessRule('Only a submitted assessment can be approved or returned.');
        }
        $note = trim((string) $note);
        if (! $approve && mb_strlen($note) < 10) {
            throw new BusinessRule('Say what has to be corrected, in at least 10 characters.');
        }
        $fa->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $user->id, 'decided_at' => now(), 'decision_note' => $note ?: null]);
        Notifier::supplier($fa->supplier_id, "Field assessment {$fa->number} was " . ($approve ? 'approved.' : "returned for correction: {$note}"), route('assessments.show', $fa), 'Field');
        Audit::log('Field', ($approve ? 'Approved' : 'Returned') . " field assessment {$fa->number}", $fa->rfq, $fa->rfq?->number ?? $fa->number, supplierId: $fa->supplier_id);

        return $fa;
    }
}
