<?php

namespace App\Livewire\Market;

use App\Exceptions\BusinessRule;
use App\Livewire\Concerns\Page;
use App\Models\Market;
use App\Models\Store;
use App\Models\User;
use App\Support\Audit;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Markets & stores')]
class Admin extends Component
{
    use Page;

    public ?int $editId = null;

    public array $m = ['name' => '', 'city' => '', 'address' => '', 'status' => 'active'];

    public array $s = ['name' => '', 'market_id' => '', 'stall' => '', 'phone' => '', 'status' => 'active', 'members' => []];

    public function mount(): void
    {
        $this->need('catalog', 'A');
    }

    public function market(?int $id = null): void
    {
        $this->editId = $id;
        $x = $id ? Market::findOrFail($id) : null;
        $this->m = ['name' => $x?->name ?? '', 'city' => (string) $x?->city, 'address' => (string) $x?->address, 'status' => $x?->status ?? 'active'];
        $this->open('market');
    }

    public function saveMarket(): void
    {
        $this->need('catalog', 'A');
        if ($this->attempt(function () {
            $name = trim($this->m['name']);
            if ($name === '' || Market::where('name', $name)->where('id', '!=', (int) $this->editId)->exists()) {
                throw new BusinessRule('Give the market a name that is not used yet.');
            }
            $x = Market::updateOrCreate(['id' => $this->editId], ['name' => $name, 'city' => trim($this->m['city']) ?: null, 'address' => trim($this->m['address']) ?: null, 'status' => $this->m['status'] === 'closed' ? 'closed' : 'active']);
            Audit::log('Catalog', ($this->editId ? 'Changed' : 'Added') . " market {$x->name}");
        }, 'Market saved.')) {
            $this->modal = null;
        }
    }

    public function store(?int $id = null): void
    {
        $this->editId = $id;
        $x = $id ? Store::findOrFail($id) : null;
        $this->s = ['name' => $x?->name ?? '', 'market_id' => (string) ($x?->market_id ?? ''), 'stall' => (string) $x?->stall, 'phone' => (string) $x?->phone, 'status' => $x?->status ?? 'active', 'members' => $x ? $x->users()->pluck('id')->map(fn ($i) => (string) $i)->all() : []];
        $this->open('store');
    }

    public function saveStore(): void
    {
        $this->need('catalog', 'A');
        if ($this->attempt(function () {
            $name = trim($this->s['name']);
            if ($name === '' || Store::where('name', $name)->where('id', '!=', (int) $this->editId)->exists()) {
                throw new BusinessRule('Give the store a name that is not used yet.');
            }
            if (! Market::whereKey($this->s['market_id'])->exists()) {
                throw new BusinessRule('Choose the market the store stands in.');
            }
            $x = Store::updateOrCreate(['id' => $this->editId], ['name' => $name, 'market_id' => (int) $this->s['market_id'], 'stall' => trim($this->s['stall']) ?: null, 'phone' => trim($this->s['phone']) ?: null, 'status' => $this->s['status'] === 'paused' ? 'paused' : 'active']);
            $ids = User::whereIn('id', $this->s['members'])->whereIn('role', ['seller', 'market_worker'])->pluck('id');
            User::where('store_id', $x->id)->whereNotIn('id', $ids)->update(['store_id' => null]);
            User::whereIn('id', $ids)->update(['store_id' => $x->id]);
            Audit::log('Catalog', ($this->editId ? 'Changed' : 'Added') . " store {$x->name} with {$ids->count()} member(s)");
        }, 'Store saved.')) {
            $this->modal = null;
        }
    }

    public function retire(int $id): void
    {
        $this->need('catalog', 'A');
        $p = \App\Models\Product::findOrFail($id);
        $p->update(['status' => $p->status === 'active' ? 'retired' : 'active']);
        Audit::log('Catalog', ($p->status === 'active' ? 'Restored' : 'Retired') . " product {$p->sku}", $p, $p->sku);
        $this->toast($p->status === 'active' ? "{$p->sku} is on sale again." : "{$p->sku} is retired and hidden from the marketplace.");
    }

    public function render()
    {
        return view('livewire.market.admin', ['products' => \App\Models\Product::with('category')->withCount('offers')->withMin('offers', 'price_cents')->orderBy('name')->limit(200)->get(),'markets' => Market::withCount('stores')->orderBy('name')->get(), 'stores' => Store::with('market', 'users')->withCount('offers')->orderBy('name')->get(),
            'people' => $this->modal === 'store' ? User::whereIn('role', ['seller', 'market_worker'])->where('status', '!=', 'disabled')->with('store')->orderBy('name')->get() : collect()]);
    }
}
