<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\CartItem;
use App\Models\MarketOrder;
use App\Models\Offer;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\Sequence;
use App\Models\User;
use App\Models\WorkerTask;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Notifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The marketplace: a basket that can hold offers from several stores, one order per store at checkout,
 * and the store-side steps (confirm, pick, deliver). Kept apart from purchase orders on purpose.
 */
class MarketService
{
    private function qty(?string $typed, string $what): int
    {
        try {
            $q = Money::parseQty($typed);
        } catch (InvalidArgumentException) {
            throw new BusinessRule("The quantity for {$what} is not a number.");
        }
        if (! $q || $q <= 0) {
            throw new BusinessRule("Enter a quantity above zero for {$what}.");
        }

        return $q;
    }

    private function check(Offer $offer, int $qty): void
    {
        $name = $offer->product->name;
        if ($offer->status !== 'active' || $offer->store->status !== 'active' || $offer->product->status !== 'active') {
            throw new BusinessRule("\"{$name}\" from {$offer->store->name} is no longer on sale.");
        }
        if ($qty < $offer->moq_milli) {
            throw new BusinessRule("\"{$name}\" from {$offer->store->name}: the smallest order is " . Money::qty($offer->moq_milli) . " {$offer->product->unit}.");
        }
        if ($qty > $offer->stock_milli) {
            throw new BusinessRule("\"{$name}\" from {$offer->store->name}: only " . Money::qty($offer->stock_milli) . " {$offer->product->unit} in stock.");
        }
    }

    /** Put an offer in the basket, or change its quantity when it is already there. */
    public function add(User $user, Offer $offer, string|int $quantity, ?RfqItem $for = null): CartItem
    {
        if (! $user->canShop()) {
            throw new BusinessRule('Your role cannot buy in the marketplace.');
        }
        $qty = is_int($quantity) ? $quantity : $this->qty($quantity, "\"{$offer->product->name}\"");
        $this->check($offer->loadMissing('product', 'store'), $qty);

        return CartItem::updateOrCreate(['user_id' => $user->id, 'offer_id' => $offer->id], ['quantity_milli' => $qty, 'rfq_item_id' => $for?->id])->fresh();
    }

    public function remove(User $user, int $cartItemId): void
    {
        CartItem::where('user_id', $user->id)->whereKey($cartItemId)->delete();
    }

    /** Basket lines grouped by store, with totals. @return Collection<int, array{store:\App\Models\Store,lines:Collection,total:int}> */
    public function basket(User $user): Collection
    {
        return CartItem::where('user_id', $user->id)->with('offer.product', 'offer.store.market', 'rfqItem.rfq')->get()
            ->each(fn (CartItem $c) => $c->setAttribute('line_cents', Money::lineTotal($c->offer->price_cents, $c->quantity_milli)))
            ->groupBy(fn (CartItem $c) => $c->offer->store_id)
            ->map(fn (Collection $lines) => ['store' => $lines->first()->offer->store, 'lines' => $lines, 'total' => (int) $lines->sum('line_cents')])->values();
    }

    /** One order per store. Stock is taken at this moment; if any line fails, nothing is ordered. @return Collection<int, MarketOrder> */
    public function checkout(User $user, ?string $deliverTo = null, ?string $note = null): Collection
    {
        if (! $user->canShop()) {
            throw new BusinessRule('Your role cannot buy in the marketplace.');
        }
        if ($user->role === 'supplier' && ! $user->supplier?->isActive()) {
            throw new BusinessRule('Your supplier account is not active.');
        }
        $basket = $this->basket($user);
        if ($basket->isEmpty()) {
            throw new BusinessRule('Your basket is empty.');
        }
        if (trim((string) $deliverTo) === '') {
            throw new BusinessRule('Say where the goods should be delivered.');
        }

        return DB::transaction(function () use ($user, $basket, $deliverTo, $note) {
            $orders = collect();
            foreach ($basket as $group) {
                $currencies = $group['lines']->pluck('offer.currency')->unique();
                if ($currencies->count() > 1) {
                    throw new BusinessRule("{$group['store']->name} prices are in different currencies. Order them separately.");
                }
                $rfqIds = $group['lines']->pluck('rfqItem.rfq_id')->filter()->unique();
                $order = MarketOrder::create(['number' => Sequence::next('MO'), 'buyer_id' => $user->id, 'supplier_id' => $user->role === 'supplier' ? $user->supplier_id : null, 'store_id' => $group['store']->id,
                    'rfq_id' => $rfqIds->count() === 1 ? $rfqIds->first() : null, 'currency' => $currencies->first(), 'deliver_to' => trim($deliverTo), 'note' => trim((string) $note) ?: null]);
                $total = 0;
                foreach ($group['lines'] as $line) {
                    $offer = Offer::whereKey($line->offer_id)->lockForUpdate()->with('product', 'store')->first();
                    $this->check($offer, $line->quantity_milli);
                    $cents = Money::lineTotal($offer->price_cents, $line->quantity_milli);
                    $order->items()->create(['offer_id' => $offer->id, 'product_id' => $offer->product_id, 'rfq_item_id' => $line->rfq_item_id, 'name' => $offer->product->name, 'sku' => $offer->product->sku, 'unit' => $offer->product->unit,
                        'quantity_milli' => $line->quantity_milli, 'unit_price_cents' => $offer->price_cents, 'total_cents' => $cents]);
                    $offer->decrement('stock_milli', $line->quantity_milli);
                    $total += $cents;
                }
                $order->update(['total_cents' => $total]);
                Notifier::send(User::where('store_id', $group['store']->id)->where('role', 'seller')->get(), "New marketplace order {$order->number}: " . Money::format($total, $order->currency) . '.', route('market.orders.show', $order), 'Marketplace');
                Audit::log('Marketplace', "Placed order {$order->number} with {$group['store']->name}", $order, $order->number, supplierId: $order->supplier_id);
                $orders->push($order->fresh());
            }
            CartItem::where('user_id', $user->id)->delete();

            return $orders;
        });
    }

    private function sells(MarketOrder $order, User $user): bool
    {
        return in_array($user->role, ['seller', 'market_worker'], true) && $user->store_id === $order->store_id;
    }

    /** The step this user may take on the order now, or null. @return array{to:string,label:string}|null */
    public function nextFor(MarketOrder $order, User $user): ?array
    {
        if (! $this->sells($order, $user) || ! $user->allowed('marketplace', 'E')) {
            return null;
        }

        return match ($order->status) {
            'placed' => $user->role === 'seller' ? ['to' => 'confirmed', 'label' => 'Confirm order'] : null,
            'confirmed' => ['to' => 'ready', 'label' => 'Mark as picked and ready'],
            'ready' => ['to' => 'delivered', 'label' => 'Mark as delivered'],
            default => null,
        };
    }

    public function advance(MarketOrder $order, User $user): MarketOrder
    {
        $step = $this->nextFor($order, $user) ?? throw new BusinessRule('You cannot move this order to its next step.');

        return DB::transaction(function () use ($order, $user, $step) {
            $order->update(['status' => $step['to'], $step['to'] . '_at' => now()]);
            if ($step['to'] === 'confirmed') {
                $order->tasks()->create(['store_id' => $order->store_id, 'type' => 'pick']);
                Notifier::send(User::where('store_id', $order->store_id)->where('role', 'market_worker')->get(), "Pick the goods for order {$order->number}.", route('market.tasks'), 'Marketplace');
            } else {
                $order->tasks()->where('type', $step['to'] === 'ready' ? 'pick' : 'deliver')->where('status', 'open')->update(['status' => 'done', 'done_by' => $user->id, 'done_at' => now()]);
                if ($step['to'] === 'ready') {
                    $order->tasks()->create(['store_id' => $order->store_id, 'type' => 'deliver']);
                }
            }
            Notifier::send($order->buyer, "Marketplace order {$order->number} is now " . mb_strtolower(MarketOrder::STATUSES[$step['to']]) . '.', route('market.orders.show', $order), 'Marketplace');
            Audit::log('Marketplace', 'Order moved to ' . MarketOrder::STATUSES[$step['to']], $order, $order->number, supplierId: $order->supplier_id);

            return $order;
        });
    }

    /** The buyer may cancel until the store confirms; the store may cancel until the goods are ready. Stock goes back. */
    public function cancel(MarketOrder $order, User $user, string $reason): MarketOrder
    {
        $buyerSide = $order->buyer_id === $user->id || ($user->role === 'supplier' && $user->supplier_id !== null && $user->supplier_id === $order->supplier_id) || $user->isAdmin();
        $ok = ($buyerSide && $order->status === 'placed') || ($this->sells($order, $user) && $user->role === 'seller' && in_array($order->status, ['placed', 'confirmed'], true));
        if (! $ok) {
            throw new BusinessRule('This order can no longer be cancelled by you.');
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw new BusinessRule('Give a short reason for cancelling.');
        }

        return DB::transaction(function () use ($order, $user, $reason, $buyerSide) {
            foreach ($order->items as $line) {
                Offer::whereKey($line->offer_id)->increment('stock_milli', $line->quantity_milli);
            }
            $order->tasks()->where('status', 'open')->update(['status' => 'cancelled']);
            $order->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => trim($reason)]);
            $to = $this->sells($order, $user) ? [$order->buyer] : User::where('store_id', $order->store_id)->where('role', 'seller')->get();
            Notifier::send($to, "Marketplace order {$order->number} was cancelled: " . trim($reason), route('market.orders.show', $order), 'Marketplace');
            Audit::log('Marketplace', 'Cancelled order', $order, $order->number, supplierId: $order->supplier_id);

            return $order;
        });
    }

    /** Words and sizes of an item name worth searching for. @return array<int, string> */
    public static function keywords(string $name): array
    {
        $stop = ['and', 'the', 'for', 'with', 'per', 'bars', 'bar', 'set', 'pcs', 'size', 'type', 'grade'];
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($name), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter($words, fn ($w) => ! in_array($w, $stop, true) && (preg_match('/^\d+$/', $w) ? strlen($w) >= 2 : mb_strlen($w) >= 3))));
    }

    /** How many of the key words appear in a product name, as whole words. */
    public static function matches(array $words, string $productName): int
    {
        $have = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($productName), -1, PREG_SPLIT_NO_EMPTY);

        return count(array_intersect($words, $have));
    }

    /**
     * Live offers that could cover one request item: same unit, and at least half of the item's key words in the product name.
     * Closest match first, then cheapest.
     */
    public function offersFor(RfqItem $item): Collection
    {
        $words = self::keywords($item->name);
        $text = array_values(array_filter($words, fn ($w) => ! preg_match('/^\d+$/', $w)));
        if (! $text) {
            return collect();
        }
        $need = max(1, (int) ceil(count($words) / 2));

        return Offer::live()->with('product', 'store.market')->whereHas('product', fn ($p) => $p->where('unit', $item->unit)->where(fn ($w) => collect($text)->each(fn ($word) => $w->orWhere('name', 'like', "%{$word}%"))))->get()
            ->each(fn (Offer $o) => $o->setAttribute('match', self::matches($words, $o->product->name)))
            ->filter(fn (Offer $o) => $o->match >= $need)
            ->sortBy([['match', 'desc'], ['price_cents', 'asc'], ['lead_days', 'asc']])->values()
            ->each(fn (Offer $o) => $o->setAttribute('exact', $o->match === count($words)));
    }

    /** For every item of the request: the matching offers and the cheapest one that has the stock. */
    public function sourcingPlan(Rfq $rfq): Collection
    {
        return $rfq->items->map(function (RfqItem $item) {
            $offers = $this->offersFor($item);
            $best = $offers->first(fn (Offer $o) => $o->stock_milli >= max($item->quantity_milli, $o->moq_milli));

            return ['item' => $item, 'offers' => $offers, 'best' => $best, 'qty' => $best ? max($item->quantity_milli, $best->moq_milli) : $item->quantity_milli];
        });
    }

    /** Put the cheapest offer for every coverable item into the basket. Returns how many items were covered. */
    public function addCheapestMix(User $user, Rfq $rfq): int
    {
        $n = 0;
        foreach ($this->sourcingPlan($rfq) as $row) {
            if ($row['best']) {
                $this->add($user, $row['best'], $row['qty'], $row['item']);
                $n++;
            }
        }
        if ($n === 0) {
            throw new BusinessRule('No store has a matching product in stock for any item of this request.');
        }

        return $n;
    }
}
