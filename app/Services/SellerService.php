<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** What a store does with its own price list: add and change offers, pause them, import a file. */
class SellerService
{
    /** @param array{sku:string,name?:string,unit?:string,category?:string,price:string,moq?:string,stock?:string,lead_days?:string|int} $row */
    public function saveOffer(Store $store, array $row, ?Offer $offer = null): Offer
    {
        $sku = mb_strtoupper(trim((string) ($row['sku'] ?? '')));
        if ($sku === '' || ! preg_match('/^[A-Z0-9][A-Z0-9._-]{1,39}$/', $sku)) {
            throw new BusinessRule('The SKU needs 2 to 40 letters, digits, dots or dashes.');
        }
        try {
            $price = Money::parse($row['price'] ?? null);
            $moq = Money::parseQty(($row['moq'] ?? '') === '' ? '1' : $row['moq']);
            $stock = Money::parseQty(($row['stock'] ?? '') === '' ? '0' : $row['stock']);
        } catch (InvalidArgumentException) {
            throw new BusinessRule("{$sku}: price, smallest order or stock is not a number.");
        }
        if ($price === null || $price <= 0) {
            throw new BusinessRule("{$sku}: the price must be above zero.");
        }
        if ($moq <= 0 || $stock < 0) {
            throw new BusinessRule("{$sku}: the smallest order must be above zero and stock cannot be negative.");
        }
        $lead = (int) (($row['lead_days'] ?? '') === '' ? 1 : $row['lead_days']);
        if ($lead < 0 || $lead > 365) {
            throw new BusinessRule("{$sku}: lead time is between 0 and 365 days.");
        }
        if ($offer && $offer->store_id !== $store->id) {
            throw new BusinessRule('This offer belongs to another store.');
        }

        return DB::transaction(function () use ($store, $row, $sku, $price, $moq, $stock, $lead, $offer) {
            $product = $offer?->product ?? Product::where('sku', $sku)->first();
            if (! $product) {
                if (trim((string) ($row['name'] ?? '')) === '' || trim((string) ($row['unit'] ?? '')) === '') {
                    throw new BusinessRule("{$sku} is a new product: it needs a name and a unit.");
                }
                $cat = trim((string) ($row['category'] ?? '')) !== '' ? Category::where('name', trim($row['category']))->orWhere('id', (int) $row['category'])->value('id') : null;
                $product = Product::create(['sku' => $sku, 'name' => trim($row['name']), 'unit' => trim($row['unit']), 'category_id' => $cat]);
                Audit::log('Catalog', "Added product {$sku}", $product, $sku);
            }
            $offer = Offer::updateOrCreate(['product_id' => $product->id, 'store_id' => $store->id], ['price_cents' => $price, 'currency' => $row['currency'] ?? 'USD', 'moq_milli' => $moq, 'stock_milli' => $stock, 'lead_days' => $lead]);

            return $offer->fresh();
        });
    }

    public function toggle(Offer $offer, Store $store): Offer
    {
        if ($offer->store_id !== $store->id) {
            throw new BusinessRule('This offer belongs to another store.');
        }
        $offer->update(['status' => $offer->status === 'active' ? 'paused' : 'active']);

        return $offer;
    }

    /**
     * Import a price list. The first row holds the headings: sku, name, unit, category, price, moq, stock, lead_days.
     * Rows that fail are reported and skipped; the others are saved.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{saved:int,errors:array<int,string>}
     */
    public function import(Store $store, array $rows): array
    {
        $head = array_map(fn ($h) => mb_strtolower(trim((string) $h)), array_shift($rows) ?? []);
        if (! in_array('sku', $head, true) || ! in_array('price', $head, true)) {
            throw new BusinessRule('The file needs at least the columns "sku" and "price" in its first row.');
        }
        $saved = 0;
        $errors = [];
        foreach ($rows as $i => $cells) {
            $row = [];
            foreach ($head as $c => $name) {
                $row[$name] = is_scalar($cells[$c] ?? null) ? trim((string) $cells[$c]) : '';
            }
            if (implode('', $row) === '') {
                continue;
            }
            try {
                $this->saveOffer($store, $row);
                $saved++;
            } catch (BusinessRule $e) {
                $errors[] = 'Row ' . ($i + 2) . ': ' . $e->getMessage();
            }
            if (count($errors) >= 50) {
                break;
            }
        }
        Audit::log('Catalog', "Imported price list for {$store->name}: {$saved} saved, " . count($errors) . ' skipped');

        return ['saved' => $saved, 'errors' => $errors];
    }
}
