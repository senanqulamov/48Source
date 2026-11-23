<?php

namespace App\Livewire;

use App\Exceptions\BusinessRule;
use App\Livewire\Concerns\Page;
use App\Models\FieldAssessment;
use App\Models\User;
use App\Models\WorkerTask;
use App\Support\Audit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Workers')]
class Workers extends Component
{
    use Page;

    public array $w = ['name' => '', 'email' => ''];

    public array $assign = [];

    public function mount(): void
    {
        $u = $this->me();
        abort_unless(($u->role === 'seller' && $u->store_id) || ($u->role === 'supplier' && $u->supplier_id), 403);
    }

    private function scope()
    {
        $u = $this->me();

        return $u->role === 'seller' ? User::where('role', 'market_worker')->where('store_id', $u->store_id) : User::where('role', 'supplier_worker')->where('supplier_id', $u->supplier_id);
    }

    /** The owner adds a worker of their own store or company; the worker sets a password through the emailed link. */
    public function add(): void
    {
        $u = $this->me();
        if ($this->attempt(function () use ($u) {
            $email = mb_strtolower(trim($this->w['email']));
            if (mb_strlen(trim($this->w['name'])) < 2 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new BusinessRule('Enter the name and a valid email address.');
            }
            if (User::where('email', $email)->exists()) {
                throw new BusinessRule('An account with this email already exists.');
            }
            if ($this->scope()->count() >= 25) {
                throw new BusinessRule('You already have 25 workers. Ask an administrator to add more.');
            }
            $worker = User::create(['name' => trim($this->w['name']), 'email' => $email, 'password' => Hash::make(Str::random(40)), 'status' => 'invited', 'email_verified_at' => now()] + ($u->role === 'seller' ? ['role' => 'market_worker', 'store_id' => $u->store_id] : ['role' => 'supplier_worker', 'supplier_id' => $u->supplier_id]));
            Password::sendResetLink(['email' => $worker->email]);
            Audit::log('Users', "Added worker {$worker->name}", $worker, $worker->email, supplierId: $u->supplier_id);
        }, 'Worker added. An email with a link to set the password was sent.')) {
            $this->reset('w');
            $this->modal = null;
        }
    }

    public function toggle(int $id): void
    {
        $worker = $this->scope()->findOrFail($id);
        $worker->update(['status' => $worker->status === 'disabled' ? 'active' : 'disabled']);
        Audit::log('Users', ($worker->status === 'disabled' ? 'Deactivated' : 'Reactivated') . " worker {$worker->name}", $worker, $worker->email, supplierId: $this->me()->supplier_id);
        $this->toast($worker->status === 'disabled' ? "{$worker->name} can no longer sign in." : "{$worker->name} can sign in again.");
    }

    /** Supplier: give an open field assessment to one of the field workers. */
    public function assignTo(int $assessmentId): void
    {
        $u = $this->me();
        abort_unless($u->role === 'supplier', 403);
        $fa = FieldAssessment::where('supplier_id', $u->supplier_id)->whereIn('status', ['assigned', 'in_progress', 'rejected'])->findOrFail($assessmentId);
        $worker = User::where('supplier_id', $u->supplier_id)->whereIn('role', ['supplier', 'supplier_worker'])->where('status', 'active')->find($this->assign[$assessmentId] ?? null);
        if (! $worker) {
            $this->toast('Choose an active person of your company.', 'alert');

            return;
        }
        $fa->update(['assigned_to' => $worker->id]);
        \App\Support\Notifier::send($worker, "Field assessment {$fa->number} was assigned to you.", route('assessments.show', $fa), 'Field');
        $this->toast("{$fa->number} assigned to {$worker->name}.");
    }

    public function render()
    {
        $u = $this->me();
        $seller = $u->role === 'seller';
        $workers = $this->scope()->orderBy('name')->get();
        $load = $seller ? WorkerTask::where('store_id', $u->store_id)->where('status', 'done')->selectRaw('done_by, count(*) as n')->groupBy('done_by')->pluck('n', 'done_by')
            : FieldAssessment::where('supplier_id', $u->supplier_id)->whereIn('status', ['assigned', 'in_progress', 'rejected'])->selectRaw('assigned_to, count(*) as n')->groupBy('assigned_to')->pluck('n', 'assigned_to');

        return view('livewire.workers', ['workers' => $workers, 'seller' => $seller, 'load' => $load, 'place' => $seller ? $u->store->load('market') : $u->supplier,
            'open' => $seller ? collect() : FieldAssessment::where('supplier_id', $u->supplier_id)->whereIn('status', ['assigned', 'in_progress', 'rejected'])->with('assignee', 'rfq')->orderBy('due_on')->get(),
            'people' => $seller ? collect() : User::where('supplier_id', $u->supplier_id)->whereIn('role', ['supplier', 'supplier_worker'])->where('status', 'active')->orderBy('name')->get()]);
    }
}
