<?php

namespace App\Livewire\Supplier\Field\Rfq;

use App\Livewire\Traits\Alert;
use App\Models\FieldAssessment;
use App\Models\Request;
use App\Models\RfqWorkerAssignment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use Alert, WithFileUploads;

    public Request $request;
    public ?RfqWorkerAssignment $assignment = null;
    public string $newNote = '';
    public $evidence = null;

    private const CHECKLIST = [
        'Access and unloading conditions',
        'Storage area suitability',
        'Site contact and induction confirmed',
    ];

    public function mount(Request $request): void
    {
        abort_unless(auth()->user()?->hasRole('supplier_worker'), 403);
        $this->assignment = RfqWorkerAssignment::query()
            ->where('worker_id', auth()->id())
            ->where('request_id', $request->id)
            ->firstOrFail();

        $this->request = $request->load(['buyer', 'items', 'latestFieldAssessment']);
    }

    private function ownedAssignment(): RfqWorkerAssignment
    {
        abort_unless(auth()->user()?->hasRole('supplier_worker'), 403);

        return RfqWorkerAssignment::query()->whereKey($this->assignment?->id)
            ->where('request_id', $this->request->id)
            ->where('worker_id', auth()->id())->firstOrFail();
    }

    public function start(): void
    {
        DB::transaction(function () {
            $assignment = RfqWorkerAssignment::query()->whereKey($this->ownedAssignment()->id)
                ->lockForUpdate()->firstOrFail();
            abort_if($assignment->status === 'done', 403);

            foreach (self::CHECKLIST as $label) {
                DB::table('rfq_worker_checklist_items')->updateOrInsert(
                    ['assignment_id' => $assignment->id, 'label' => $label],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }

            if ($assignment->status === 'pending') {
                $assignment->update(['status' => 'in_progress']);
            }
            $this->assignment = $assignment->refresh();
        });
    }

    public function setResult(int $itemId, string $result): void
    {
        abort_unless(in_array($result, ['pass', 'fail'], true), 422);
        DB::transaction(function () use ($itemId, $result) {
            $assignment = RfqWorkerAssignment::query()->whereKey($this->ownedAssignment()->id)
                ->lockForUpdate()->firstOrFail();
            abort_if($assignment->status === 'done', 403);
            $item = DB::table('rfq_worker_checklist_items')
                ->where('assignment_id', $assignment->id)->where('id', $itemId)->first();
            abort_unless($item !== null, 404);

            DB::table('rfq_worker_checklist_items')->where('id', $itemId)
                ->update(['result' => $item->result === $result ? null : $result, 'updated_at' => now()]);
            if ($assignment->status === 'pending') {
                $assignment->update(['status' => 'in_progress']);
            }
            $this->assignment = $assignment->refresh();
        });
    }

    public function addNote(): void
    {
        $this->validate(['newNote' => ['required', 'string', 'min:2', 'max:4000']]);
        $assignment = $this->ownedAssignment();
        abort_if($assignment->status === 'done', 403);
        DB::table('rfq_worker_notes')->insert([
            'assignment_id' => $assignment->id,
            'worker_id' => auth()->id(),
            'body' => trim($this->newNote),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->newNote = '';
    }

    public function uploadEvidence(): void
    {
        $this->validate(['evidence' => ['required', 'image', 'max:5120']]);
        $assignment = $this->ownedAssignment();
        abort_if($assignment->status === 'done', 403);

        $original = basename($this->evidence->getClientOriginalName());
        $path = $this->evidence->store('field-assessments/'.$assignment->id, 'local');
        DB::table('rfq_worker_evidence')->insert([
            'assignment_id' => $assignment->id,
            'worker_id' => auth()->id(),
            'storage_path' => $path,
            'original_name' => $original,
            'byte_size' => $this->evidence->getSize(),
            'mime_type' => $this->evidence->getMimeType(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->reset('evidence');
    }

    public function downloadEvidence(int $id)
    {
        $assignment = $this->ownedAssignment();
        $file = DB::table('rfq_worker_evidence')->where('assignment_id', $assignment->id)
            ->where('id', $id)->first();
        abort_unless($file !== null, 404);

        return Storage::disk('local')->download($file->storage_path, $file->original_name);
    }

    public function submit(): void
    {
        DB::transaction(function () {
            $assignment = RfqWorkerAssignment::query()->whereKey($this->ownedAssignment()->id)
                ->lockForUpdate()->firstOrFail();
            abort_if($assignment->status === 'done', 403);

            $checks = DB::table('rfq_worker_checklist_items')
                ->where('assignment_id', $assignment->id)->get();
            $unanswered = $checks->whereNull('result')->count();
            if ($checks->count() !== count(self::CHECKLIST) || $unanswered > 0) {
                throw ValidationException::withMessages([
                    'checklist' => 'Answer every checklist item before submitting. Remaining: '.
                        (count(self::CHECKLIST) - $checks->count() + $unanswered),
                ]);
            }

            $worker = auth()->user();
            abort_unless($worker && $worker->supplier_id, 403, 'Worker needs a supplier owner.');
            $failed = $checks->where('result', 'fail')->count();
            $notes = DB::table('rfq_worker_notes')->where('assignment_id', $assignment->id)
                ->orderBy('created_at')->pluck('body')->implode("\n");
            $photos = DB::table('rfq_worker_evidence')->where('assignment_id', $assignment->id)
                ->orderBy('id')->pluck('storage_path')->all();

            $assessment = FieldAssessment::query()->firstOrNew([
                'request_id' => $assignment->request_id,
                'supplier_id' => $worker->supplier_id,
            ]);
            abort_if($assessment->exists &&
                in_array($assessment->status, ['submitted', 'approved'], true), 409,
                'A final assessment already exists for this supplier and RFQ.');
            $assessment->fill([
                'site_location' => $this->request->delivery_location,
                'technical_compliance' => $failed ? 'Failed' : 'Passed',
                'technical_feasibility' => $failed ? 'Conditional assessment: '.$failed.' failed checks' : 'Passed all checks',
                'notes' => $notes,
                'field_notes' => $notes,
                'photos' => $photos,
                'status' => 'submitted',
                'completed_at' => now(),
                'submitted_at' => now(),
            ]);
            $assessment->save();
            if ($this->request->requires_field_assessment) {
                $this->request->update([
                    'field_assessment_status' => 'completed',
                    'field_assessment_completed_at' => now(),
                ]);
            }
            $assignment->update(['status' => 'done']);
            $this->assignment = $assignment->refresh();
        });
        $this->request->refresh()->load('latestFieldAssessment');
        $this->success(__('Assessment submitted. It is now read-only.'));
    }

    public function render(): View
    {
        $assignment = $this->ownedAssignment();
        $checks = DB::table('rfq_worker_checklist_items')
            ->where('assignment_id', $assignment->id)->orderBy('id')->get();
        $notes = DB::table('rfq_worker_notes')
            ->where('assignment_id', $assignment->id)->orderByDesc('created_at')->get();
        $files = DB::table('rfq_worker_evidence')
            ->where('assignment_id', $assignment->id)->orderByDesc('id')->get();

        return view('livewire.supplier.field.rfq.show', compact('checks', 'notes', 'files'))
            ->layout('layouts.app');
    }
}
