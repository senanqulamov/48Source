<?php

namespace App\Livewire\Messages;

use App\Livewire\Concerns\Page;
use App\Models\Conversation;
use App\Models\Supplier;
use App\Services\MessageService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Messages')]
class Index extends Component
{
    use Page;

    #[Url(except: null)]
    public ?int $c = null;

    public string $find = '';

    public string $body = '';

    public array $new = ['supplier_id' => '', 'subject' => '', 'body' => ''];

    public function mount(): void
    {
        abort_unless($this->me()->isSourcing() || $this->me()->role === 'supplier', 403);
    }

    public function pick(int $id): void
    {
        $this->c = $id;
        $this->body = '';
    }

    public function send(MessageService $service): void
    {
        $conv = Conversation::visibleTo($this->me())->findOrFail($this->c);
        if ($this->attempt(fn () => $service->post($conv, $this->me(), $this->body))) {
            $this->body = '';
        }
    }

    public function start(MessageService $service): void
    {
        $u = $this->me();
        $conv = null;
        if ($this->attempt(function () use ($service, $u, &$conv) {
            $supplier = $u->role === 'supplier' ? $u->supplier : (Supplier::find($this->new['supplier_id']) ?? throw new \App\Exceptions\BusinessRule('Choose a supplier.'));
            $conv = $service->start($u, $supplier, $this->new['subject'], $this->new['body']);
        }, 'Message sent.')) {
            $this->reset('new');
            $this->modal = null;
            $this->c = $conv->id;
        }
    }

    public function render(MessageService $service)
    {
        $u = $this->me();
        $list = Conversation::visibleTo($u)->with(['supplier', 'reads' => fn ($r) => $r->where('user_id', $u->id)])->withCount('messages')->withMax(['messages as last_other_id' => fn ($m) => $m->where('user_id', '!=', $u->id)], 'id')
            ->when($this->find !== '', fn ($q) => $q->where(fn ($w) => $w->where('subject', 'like', "%{$this->find}%")->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$this->find}%"))))
            ->orderByDesc('last_message_at')->limit(100)->get();
        $open = $this->c ? Conversation::visibleTo($u)->with('supplier', 'messages.user')->find($this->c) : null;
        if ($open) {
            $service->markRead($open, $u);
        }

        return view('livewire.messages.index', ['list' => $list, 'open' => $open, 'sourcing' => $u->isSourcing(), 'suppliers' => $this->modal === 'new' && $u->isSourcing() ? Supplier::whereIn('status', ['active', 'pending'])->orderBy('name')->pluck('name', 'id') : collect()]);
    }
}
