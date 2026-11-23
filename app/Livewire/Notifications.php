<?php

namespace App\Livewire;

use App\Livewire\Concerns\Page;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Notifications')]
class Notifications extends Component
{
    use Page, WithPagination;

    public bool $unreadOnly = false;

    public function openNotice(string $id)
    {
        $n = $this->me()->notifications()->findOrFail($id);
        $n->markAsRead();

        return $this->redirect($n->data['url'] ?? route('dashboard'), navigate: true);
    }

    public function readAll(): void
    {
        $this->me()->unreadNotifications->markAsRead();
        $this->toast('All notifications marked as read.');
    }

    public function render()
    {
        $q = $this->unreadOnly ? $this->me()->unreadNotifications() : $this->me()->notifications();

        return view('livewire.notifications', ['rows' => $q->paginate(15), 'unread' => $this->me()->unreadNotifications()->count()]);
    }
}
