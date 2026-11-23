<?php

namespace App\Livewire\Users;

use App\Livewire\Concerns\Page;
use App\Livewire\Concerns\WithTable;
use App\Models\ActivityLog;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit;
use App\Support\Perm;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Users & roles')]
class Index extends Component
{
    use Page, WithTable;

    #[Url(except: 'users')]
    public string $tab = 'users';

    public string $role = 'buyer';

    public ?int $openId = null;

    public string $pane = 'details';

    public array $f = [];

    protected function sortable(): array
    {
        return ['name', 'email', 'role', 'status', 'last_login_at'];
    }

    public function show(int $id): void
    {
        $this->openId = User::findOrFail($id)->id;
        $this->pane = 'details';
        $this->open('drawer');
    }

    public function create(): void
    {
        $this->need('users', 'C');
        $this->f = ['id' => null, 'name' => '', 'email' => '', 'role' => 'buyer', 'supplier_id' => '', 'department' => ''];
        $this->open('form');
    }

    public function edit(int $id): void
    {
        $this->need('users', 'E');
        $u = User::findOrFail($id);
        $this->f = ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'supplier_id' => (string) $u->supplier_id, 'department' => (string) $u->department];
        $this->open('form');
    }

    public function save(): void
    {
        $id = $this->f['id'];
        $this->need('users', $id ? 'E' : 'C');
        $this->f['email'] = mb_strtolower(trim($this->f['email']));
        $supplierRole = in_array($this->f['role'], ['supplier', 'supplier_worker'], true);
        $this->validate([
            'f.name' => 'required|string|min:2|max:120',
            'f.email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($id)],
            'f.role' => ['required', Rule::in(array_keys(Perm::ROLES))],
            'f.supplier_id' => [$supplierRole ? 'required' : 'nullable', 'exists:suppliers,id'],
            'f.department' => 'nullable|string|max:80',
        ], ['f.supplier_id.required' => 'Choose the supplier company this person belongs to.'], ['f.name' => 'name', 'f.email' => 'email', 'f.role' => 'role', 'f.supplier_id' => 'supplier']);
        $data = ['name' => trim($this->f['name']), 'email' => $this->f['email'], 'role' => $this->f['role'], 'supplier_id' => $supplierRole ? (int) $this->f['supplier_id'] : null, 'department' => $this->f['department'] ?: null];

        if ($id) {
            $u = User::findOrFail($id);
            if ($u->id === $this->me()->id && $data['role'] !== $u->role) {
                $this->addError('f.role', 'You cannot change your own role.');

                return;
            }
            if ($data['role'] !== $u->role) {
                $this->need('users', 'A');
            }
            $before = $u->role;
            $u->update($data);
            Audit::log('Users', 'Edited user'.($before !== $u->role ? ': role changed from '.Perm::ROLES[$before].' to '.$u->roleLabel() : ''), $u);
            $this->toast("{$u->name} saved.");
        } else {
            // No password is chosen for the user: they set their own through an emailed link.
            $u = User::create($data + ['password' => Str::password(40), 'status' => 'invited']);
            Password::sendResetLink(['email' => $u->email]);
            Audit::log('Users', 'Invited user as '.$u->roleLabel(), $u);
            $this->toast("Invitation sent to {$u->email}.");
        }
        $this->close();
    }

    public function resend(int $id): void
    {
        $this->need('users', 'E');
        $u = User::findOrFail($id);
        Password::sendResetLink(['email' => $u->email]);
        Audit::log('Users', 'Sent a set-password link', $u);
        $this->toast("A set-password link was sent to {$u->email}.");
    }

    public function toggle(int $id): void
    {
        $this->need('users', 'E');
        $u = User::findOrFail($id);
        if ($u->id === $this->me()->id) {
            $this->toast('You cannot deactivate your own account.', 'alert');

            return;
        }
        if ($u->role === 'admin' && $u->status === 'active' && User::where('role', 'admin')->where('status', 'active')->count() <= 1) {
            $this->toast('This is the last active administrator. Add another one first.', 'alert');

            return;
        }
        $u->update(['status' => $u->status === 'disabled' ? ($u->email_verified_at ? 'active' : 'invited') : 'disabled']);
        Audit::log('Users', $u->status === 'disabled' ? 'Deactivated user' : 'Reactivated user', $u);
        $this->toast("{$u->name} is now ".($u->status === 'disabled' ? 'deactivated' : 'active').'.');
    }

    public function togglePerm(string $module, string $ability): void
    {
        $this->need('users', 'A');
        if (! isset(Perm::ROLES[$this->role], Perm::MODULES[$module], Perm::ABILITIES[$ability])) {
            return;
        }
        if ($this->role === 'admin' && $module === 'users') {
            $this->toast('Administrators always keep full control of Users & roles, so nobody can be locked out.', 'alert');

            return;
        }
        $now = Perm::matrix()[$this->role][$module];
        $next = str_contains($now, $ability) ? str_replace($ability, '', $now) : $now.$ability;
        // Without View the other abilities mean nothing, and any ability implies View.
        if ($ability === 'V' && ! str_contains($next, 'V')) {
            $next = '';
        } elseif ($next !== '' && ! str_contains($next, 'V')) {
            $next .= 'V';
        }
        Perm::set($this->role, $module, $next);
        Audit::log('Users', 'Changed permissions of '.Perm::ROLES[$this->role].': '.Perm::MODULES[$module].' = '.(Perm::matrix()[$this->role][$module] ?: 'none'));
    }

    protected function exportData(): array
    {
        $this->need('users');
        $rows = User::with('supplier')->orderBy('name')->get()->map(fn (User $u) => [$u->name, $u->email, $u->roleLabel(), $u->supplier?->name, $u->status, $u->last_login_at?->format('Y-m-d H:i')]);

        return ['users', ['Name', 'Email', 'Role', 'Supplier', 'Status', 'Last sign-in'], $rows];
    }

    public function render()
    {
        $this->need('users');
        $rows = User::with('supplier')
            ->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->q}%")->orWhere('email', 'like', "%{$this->q}%")))
            ->when($this->status !== '', fn ($q) => $q->where('role', $this->status))
            ->when($this->facet('st', ['active', 'invited', 'disabled']), fn ($q, $st) => $q->whereIn('status', $st))
            ->orderBy($this->sortCol('name'), $this->sort === '' ? 'asc' : $this->sortDir())->paginate($this->perPage());
        $current = $this->openId ? User::with('supplier')->find($this->openId) : null;

        return view('livewire.users.index', [
            'rows' => $rows, 'current' => $current, 'matrix' => Perm::matrix(), 'roleCounts' => User::selectRaw('role, count(*) as n')->groupBy('role')->pluck('n', 'role'),
            'suppliers' => $this->modal === 'form' ? Supplier::orderBy('name')->pluck('name', 'id') : collect(),
            'logs' => $current && $this->modal === 'drawer' && $this->pane === 'history' ? ActivityLog::where('user_id', $current->id)->orWhere(fn ($q) => $q->where('subject_type', 'user')->where('subject_id', $current->id))->latest('created_at')->limit(80)->get() : collect(),
        ]);
    }
}
