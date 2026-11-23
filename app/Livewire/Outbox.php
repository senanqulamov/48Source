<?php

namespace App\Livewire;

use App\Livewire\Concerns\Page;
use App\Livewire\Concerns\WithTable;
use App\Models\FeatureFlag;
use App\Models\OutboxEmail;
use App\Support\Notifier;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Email outbox')]
class Outbox extends Component
{
    use Page, WithTable;

    public ?int $openId = null;

    public function mount(): void
    {
        $this->need('system');
    }

    protected function sortable(): array
    {
        return ['created_at', 'status', 'to'];
    }

    public function show(int $id): void
    {
        $this->openId = $id;
        $this->open('mail');
    }

    public function resend(int $id): void
    {
        $this->need('system', 'E');
        $mail = Notifier::deliver(OutboxEmail::findOrFail($id));
        $this->toast($mail->status === 'sent' ? 'Email sent again.' : 'Sending failed: ' . $mail->error, $mail->status === 'sent' ? 'check' : 'alert');
    }

    public function render()
    {
        $base = fn () => OutboxEmail::query()->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w->where('to', 'like', "%{$this->q}%")->orWhere('subject', 'like', "%{$this->q}%")));
        $areas = OutboxEmail::query()->distinct()->orderBy('area')->pluck('area')->all();
        $rows = $base()->when($this->status !== '', fn ($q) => $q->where('status', $this->status))->when($this->facet('area', $areas), fn ($q, $a) => $q->whereIn('area', $a))->orderBy($this->sortCol('created_at'), $this->sortDir())->orderByDesc('id')->paginate($this->perPage());

        return view('livewire.outbox', ['rows' => $rows, 'counts' => $base()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'), 'all' => OutboxEmail::count(), 'areas' => array_combine($areas, $areas),
            'on' => FeatureFlag::isOn('email_notifications'), 'mailer' => config('mail.default'), 'current' => $this->modal === 'mail' ? OutboxEmail::find($this->openId) : null]);
    }
}
