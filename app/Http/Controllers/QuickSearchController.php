<?php

namespace App\Http\Controllers;

use App\Models\FieldAssessment;
use App\Models\MarketOrder;
use App\Models\Offer;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Records for the search box in the top bar. Every query goes through the
 * same visibility rules as the lists, so a role only finds what it may open.
 */
class QuickSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $out = [];
        $add = function (string $icon, string $title, string $sub, string $url) use (&$out) {
            $out[] = ['icon' => $icon, 'title' => $title, 'sub' => $sub, 'url' => $url];
        };

        if ($user->allowed('rfqs') || $user->allowed('quotes')) {
            Rfq::visibleTo($user)
                ->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('title', 'like', $like))
                ->latest('id')->limit(5)->get(['id', 'number', 'title', 'status'])
                ->each(fn ($r) => $add('file', $r->number, $r->title, $user->isSourcing() ? route('rfqs.show', $r->number) : route('quotes.form', $r->number)));
        }

        if ($user->allowed('orders')) {
            PurchaseOrder::visibleTo($user)->with('supplier:id,name')
                ->where(fn ($w) => $w->where('number', 'like', $like)->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $like)))
                ->latest('id')->limit(3)->get()
                ->each(fn ($o) => $add('box', $o->number, $user->isSourcing() ? (string) $o->supplier?->name : 'Purchase order', route('orders.show', $o->number)));
        }

        if ($user->isSourcing() && $user->allowed('suppliers')) {
            Supplier::where(fn ($w) => $w->where('name', 'like', $like)->orWhere('city', 'like', $like))
                ->orderBy('name')->limit(3)->get(['id', 'name', 'city'])
                ->each(fn ($s) => $add('building', $s->name, (string) $s->city, route('suppliers.show', $s->id)));
        }

        if ($user->allowed('marketplace')) {
            if ($user->role === 'seller' && $user->store_id) {
                Offer::where('store_id', $user->store_id)->with('product:id,name,sku')
                    ->whereHas('product', fn ($p) => $p->where('name', 'like', $like)->orWhere('sku', 'like', $like))
                    ->limit(5)->get()
                    ->each(fn ($o) => $add('box', (string) $o->product?->sku, (string) $o->product?->name, route('market.offers')));
            }
            MarketOrder::visibleTo($user)->with('store:id,name')
                ->where('number', 'like', $like)->latest('id')->limit(4)->get()
                ->each(fn ($o) => $add('truck', $o->number, (string) $o->store?->name, route('market.orders.show', $o->number)));
        }

        if ($user->allowed('field')) {
            FieldAssessment::visibleTo($user)
                ->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('title', 'like', $like))
                ->latest('id')->limit(4)->get(['id', 'number', 'title'])
                ->each(fn ($a) => $add('clip', $a->number, $a->title, route('assessments.show', $a->number)));
        }

        return response()->json(array_slice($out, 0, 14));
    }
}
