<?php

namespace App\Livewire;

use App\Livewire\Concerns\Page;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\Supplier;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Search')]
class Search extends Component
{
    use Page;

    #[Url]
    public string $q = '';

    public function render()
    {
        $u = $this->me();
        $term = trim($this->q);
        $like = "%{$term}%";
        $has = mb_strlen($term) >= 2;

        return view('livewire.search', [
            'term' => $term,
            'rfqs' => $has && $u->allowed('rfqs') ? Rfq::visibleTo($u)->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('title', 'like', $like))->latest()->limit(8)->get() : collect(),
            'orders' => $has && $u->allowed('orders') ? PurchaseOrder::visibleTo($u)->with('supplier', 'rfq')->where(fn ($w) => $w->where('number', 'like', $like)->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $like)))->latest()->limit(8)->get() : collect(),
            'more' => $has ? collect([
                ['Requisitions', $u->allowed('requisitions') ? \App\Models\Requisition::visibleTo($u)->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('title', 'like', $like))->latest('id')->limit(6)->get()->map(fn ($r) => [$r->title, $r->number . ' · ' . $r->statusLabel(), route('requisitions.show', $r)]) : collect()],
                ['Contracts', $u->allowed('contracts') ? \App\Models\Contract::visibleTo($u)->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('title', 'like', $like))->latest('id')->limit(6)->get()->map(fn ($c) => [$c->title, $c->number . ' · ' . $c->statusLabel(), route('contracts.show', $c)]) : collect()],
                ['Invoices', $u->allowed('invoices') ? \App\Models\Invoice::visibleTo($u)->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('supplier_ref', 'like', $like))->latest('id')->limit(6)->get()->map(fn ($i) => ['Invoice ' . $i->supplier_ref, $i->number . ' · ' . $i->statusLabel(), route('invoices.show', $i)]) : collect()],
                ['Information requests', $u->allowed('rfqs') ? \App\Models\Rfi::visibleTo($u)->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('title', 'like', $like))->latest('id')->limit(6)->get()->map(fn ($r) => [$r->title, $r->number . ' · ' . $r->statusLabel(), route('rfis.show', $r)]) : collect()],
                ['Field assessments', $u->allowed('field') ? \App\Models\FieldAssessment::visibleTo($u)->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('title', 'like', $like))->latest('id')->limit(6)->get()->map(fn ($a) => [$a->title, $a->number . ' · ' . $a->statusLabel(), route('assessments.show', $a)]) : collect()],
                ['Marketplace orders', $u->allowed('marketplace') ? \App\Models\MarketOrder::visibleTo($u)->with('store')->where(fn ($w) => $w->where('number', 'like', $like)->orWhereHas('items', fn ($i) => $i->where('name', 'like', $like)))->latest('id')->limit(6)->get()->map(fn ($o) => [$o->store->name, $o->number . ' · ' . $o->statusLabel(), route('market.orders.show', $o)]) : collect()],
                ['Products', $u->allowed('marketplace') ? \App\Models\Product::where('status', 'active')->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('sku', 'like', $like))->orderBy('name')->limit(6)->get()->map(fn ($p) => [$p->name, $p->sku, route('market.shop', ['q' => $p->sku])]) : collect()],
            ])->filter(fn ($g) => $g[1]->isNotEmpty())->values() : collect(),
            'suppliers' => $has && $u->isSourcing() && $u->allowed('suppliers') ? Supplier::where(fn ($w) => $w->where('name', 'like', $like)->orWhere('city', 'like', $like)->orWhere('email', 'like', $like))->orderBy('name')->limit(8)->get() : collect(),
        ]);
    }
}
