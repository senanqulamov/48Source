<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\Requisition;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\FieldAssessment;
use App\Models\MarketOrder;
use App\Support\Nav;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchSuggestionController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $term = trim((string) $request->query('q'));
        $pages = collect(Nav::for($user))->flatMap(fn ($group) => $group[1])
            ->concat([['label' => __('Settings'), 'icon' => 'sliders', 'url' => route('settings')], ['label' => __('Notifications'), 'icon' => 'bell', 'url' => route('notifications.index')]])
            ->unique('url')->filter(fn ($page) => $term === '' || str_contains(mb_strtolower($page['label']), mb_strtolower($term)))
            ->take(8)->map(fn ($page) => ['icon' => $page['icon'], 'title' => $page['label'], 'sub' => __('Open page'), 'url' => $page['url']])->values();
        if (mb_strlen($term) < 2) return response()->json(['pages' => $pages, 'records' => []]);
        $like = $term.'%'; $records = collect();
        if (($user->isSourcing() && $user->allowed('rfqs')) || $user->allowed('quotes')) $records->push(...Rfq::visibleTo($user)->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('title', 'like', $like))->latest('id')->limit(5)->get()->map(fn ($r) => ['icon' => 'file', 'title' => $r->number, 'sub' => $r->title, 'url' => $user->isSourcing() ? route('rfqs.show', $r) : route('quotes.form', $r)]));
        if ($user->allowed('orders')) $records->push(...PurchaseOrder::visibleTo($user)->where('number', 'like', $like)->latest('id')->limit(5)->get()->map(fn ($p) => ['icon' => 'award', 'title' => $p->number, 'sub' => $p->statusLabel(), 'url' => route('orders.show', $p)]));
        if ($user->isSourcing() && $user->allowed('suppliers')) $records->push(...Supplier::where('name', 'like', $like)->orderBy('name')->limit(5)->get()->map(fn ($s) => ['icon' => 'building', 'title' => $s->name, 'sub' => $s->city ?: '', 'url' => route('suppliers.index', ['q' => $s->name])]));
        if ($user->allowed('requisitions')) $records->push(...Requisition::visibleTo($user)->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('title', 'like', $like))->latest('id')->limit(5)->get()->map(fn ($r) => ['icon' => 'inbox', 'title' => $r->number, 'sub' => $r->title, 'url' => route('requisitions.show', $r)]));
        if ($user->allowed('contracts')) $records->push(...Contract::visibleTo($user)->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('title', 'like', $like))->latest('id')->limit(5)->get()->map(fn ($c) => ['icon' => 'clip', 'title' => $c->number, 'sub' => $c->title, 'url' => route('contracts.show', $c)]));
        if ($user->allowed('invoices')) $records->push(...Invoice::visibleTo($user)->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('supplier_ref', 'like', $like))->latest('id')->limit(5)->get()->map(fn ($i) => ['icon' => 'file', 'title' => $i->number, 'sub' => $i->supplier_ref ?: $i->statusLabel(), 'url' => route('invoices.show', $i)]));
        if ($user->allowed('field')) $records->push(...FieldAssessment::visibleTo($user)->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('title', 'like', $like))->latest('id')->limit(5)->get()->map(fn ($a) => ['icon' => 'camera', 'title' => $a->number, 'sub' => $a->title, 'url' => route('assessments.show', $a)]));
        if ($user->allowed('marketplace')) $records->push(...MarketOrder::visibleTo($user)->where('number', 'like', $like)->latest('id')->limit(5)->get()->map(fn ($o) => ['icon' => 'truck', 'title' => $o->number, 'sub' => $o->statusLabel(), 'url' => route('market.orders.show', $o)]));

        return response()->json(['pages' => $pages, 'records' => $records->take(15)->values()]);
    }
}
