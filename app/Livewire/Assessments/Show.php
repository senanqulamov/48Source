<?php

namespace App\Livewire\Assessments;

use App\Livewire\Concerns\Page;
use App\Models\FieldAssessment;
use App\Services\AssessmentService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Field assessment')]
class Show extends Component
{
    use Page;

    public string $number;

    public array $ans = [];

    public string $findings = '';

    public string $note = '';

    public function mount(string $number): void
    {
        $this->number = $number;
        $fa = $this->fa();
        foreach ($fa->checklist as $i => $_) {
            $a = $fa->answers[$i] ?? [];
            $this->ans[$i] = ['ok' => ($a['ok'] ?? null) === null ? '' : ($a['ok'] ? 'yes' : 'no'), 'note' => $a['note'] ?? ''];
        }
        $this->findings = (string) $fa->findings;
    }

    protected function fa(): FieldAssessment
    {
        return FieldAssessment::visibleTo($this->me())->where('number', $this->number)->firstOrFail();
    }

    private function answers(): array
    {
        return array_map(fn ($a) => ['ok' => $a['ok'] === '' ? null : $a['ok'] === 'yes', 'note' => $a['note'] ?? ''], $this->ans);
    }

    public function save(AssessmentService $service): void
    {
        $this->need('field', 'E');
        $this->attempt(fn () => $service->save($this->fa(), $this->me(), $this->answers(), $this->findings), 'Progress saved.');
    }

    public function submit(AssessmentService $service): void
    {
        $this->need('field', 'E');
        $this->attempt(function () use ($service) {
            $fa = $service->save($this->fa(), $this->me(), $this->answers(), $this->findings);
            $service->submit($fa, $this->me());
        }, 'Assessment submitted to the buyer.');
    }

    public function decide(AssessmentService $service, bool $approve): void
    {
        $this->need('field', 'A');
        abort_unless($this->me()->isSourcing(), 403);
        if ($this->attempt(fn () => $service->decide($this->fa(), $this->me(), $approve, $this->note), $approve ? 'Assessment approved.' : 'Returned to the supplier for correction.')) {
            $this->reset('note');
            $this->modal = null;
        }
    }

    public function render()
    {
        $fa = $this->fa()->load('supplier', 'rfq', 'requester', 'assignee', 'decider');
        $u = $this->me();

        return view('livewire.assessments.show', ['fa' => $fa, 'sourcing' => $u->isSourcing(),
            'canFill' => ! $u->isSourcing() && $u->allowed('field', 'E') && in_array($fa->status, ['assigned', 'in_progress', 'rejected'], true) && (! $fa->assigned_to || $fa->assigned_to === $u->id || $u->role === 'supplier')])->title($fa->number);
    }
}
