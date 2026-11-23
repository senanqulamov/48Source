<?php

namespace App\Livewire\Auth;

use App\Models\Category;
use App\Models\FeatureFlag;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit;
use App\Support\Notifier;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Register as a supplier')]
class Register extends Component
{
    public string $company = '';

    public string $name = '';

    public string $email = '';

    public string $city = '';

    public ?int $category_id = null;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        abort_unless(FeatureFlag::isOn('supplier_registration'), 404);
    }

    public function register()
    {
        $this->email = mb_strtolower(trim($this->email));
        $data = $this->validate([
            'company' => 'required|string|min:2|max:120',
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'city' => 'nullable|string|max:120',
            'category_id' => 'nullable|exists:categories,id',
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [], ['company' => 'company name', 'name' => 'your name', 'category_id' => 'category']);

        $user = DB::transaction(function () use ($data) {
            $supplier = Supplier::create(['name' => trim($data['company']), 'category_id' => $data['category_id'] ?? null, 'city' => $data['city'] ?: null, 'contact_name' => trim($data['name']), 'email' => $data['email'], 'status' => 'pending']);

            return User::create(['name' => trim($data['name']), 'email' => $data['email'], 'password' => $data['password'], 'role' => 'supplier', 'supplier_id' => $supplier->id, 'status' => 'active']);
        });
        event(new Registered($user));
        Auth::login($user);
        session()->regenerate();
        Audit::log('Supplier', 'Registered as a supplier', $user->supplier, supplierId: $user->supplier_id);
        Notifier::admins("{$user->supplier->name} registered as a supplier and is waiting for approval.", route('suppliers.index', ['status' => 'pending']), 'Supplier');

        return redirect()->route('verification.notice');
    }

    public function render()
    {
        return view('livewire.auth.register', ['categories' => Category::options()]);
    }
}
