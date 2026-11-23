<?php

namespace App\Livewire\Rfis;

use App\Livewire\Concerns\Page;
use App\Models\Rfi;
use App\Models\Supplier;
use App\Services\RfiService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Information request')]
class Form extends Component
{
    use Page;

    public ?string $number = null;

    public array $f = ['title' => '', 'description' => '', 'deadline_at' => ''];

    public array $questions = [''];

    public array $suppliers = [];

    public function mount(?string $number = null): void
    {
        abort_unless($this->me()->isSourcing(), 403);
        $this->need('rfqs', $number ? 'E' : 'C');
        $this->f['deadline_at'] = now()->addDays(7)->format('Y-m-d\T17:00');
        if ($number) {
            $r = Rfi::visibleTo($this->me())->where('number', $number)->where('status', 'draft')->firstOrFail();
            $this->number = $number;
            $this->f = ['title' => $r->title, 'description' => (string) $r->description, 'deadline_at' => $r->deadline_at?->format('Y-m-d\TH:i') ?? ''];
            $this->questions = $r->questions ?: [''];
            $this->suppliers = $r->responses()->pluck('supplier_id')->map(fn ($i) => (string) $i)->all();
        }
    }

    public function addQuestion(): void
    {
        $this->questions[] = '';
    }

    public function removeQuestion(int $i): void
    {
        unset($this->questions[$i]);
        $this->questions = array_values($this->questions) ?: [''];
    }

    public function save(RfiService $service, bool $send = false)
    {
        $this->need('rfqs', $this->number ? 'E' : 'C');
        $rfi = null;
        if ($this->attempt(function () use ($service, $send, &$rfi) {
            $existing = $this->number ? Rfi::visibleTo($this->me())->where('number', $this->number)->firstOrFail() : null;
            $rfi = $service->save($this->me(), $this->f, $this->questions, $this->suppliers, $existing);
            $this->number = $rfi->number;
            if ($send) {
                $service->publish($rfi->load('responses'));
            }
        })) {
            session()->flash('toast', $send ? "{$rfi->number} sent to the suppliers." : "{$rfi->number} saved as a draft.");

            return $this->redirectRoute('rfis.show', $rfi->number, navigate: true);
        }
    }

    public function render()
    {
        return view('livewire.rfis.form', ['list' => Supplier::where('status', 'active')->with('category')->orderBy('name')->get()]);
    }
}
