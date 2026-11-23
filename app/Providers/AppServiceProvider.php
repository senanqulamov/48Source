<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'user' => User::class, 'supplier' => Supplier::class, 'category' => Category::class,
            'rfq' => Rfq::class, 'quote' => Quote::class, 'purchase_order' => PurchaseOrder::class,
            'requisition' => \App\Models\Requisition::class, 'contract' => \App\Models\Contract::class, 'invoice' => \App\Models\Invoice::class, 'goods_receipt' => \App\Models\GoodsReceipt::class,
            'field_assessment' => \App\Models\FieldAssessment::class, 'rfi' => \App\Models\Rfi::class, 'conversation' => \App\Models\Conversation::class, 'contract_release' => \App\Models\ContractRelease::class, 'market_order' => \App\Models\MarketOrder::class, 'product' => \App\Models\Product::class,
        ]);

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(mb_strtolower((string) $r->input('email')).'|'.$r->ip()));

        // @perm('rfqs', 'C') ... @endperm
        Blade::if('perm', fn (string $module, string $ability = 'V') => (bool) auth()->user()?->allowed($module, $ability));
    }
}
