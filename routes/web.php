<?php

use App\Livewire;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SearchSuggestionController;

Route::middleware('guest')->group(function () {
    Route::view('/welcome', 'pages.landing')->name('landing');
    Route::get('/two-factor', Livewire\Auth\TwoFactor::class)->name('two-factor');
    Route::get('/login', Livewire\Auth\Login::class)->name('login');
    Route::get('/register', Livewire\Auth\Register::class)->name('register');
    Route::get('/forgot-password', Livewire\Auth\ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', Livewire\Auth\ResetPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    Route::get('/email/verify', Livewire\Auth\VerifyEmail::class)->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect()->route('dashboard')->with('toast', 'Your email is confirmed.');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
});

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('/', Livewire\Dashboard::class)->name('dashboard');
    Route::get('/search', Livewire\Search::class)->name('search');
    Route::get('/search/quick', \App\Http\Controllers\QuickSearchController::class)->name('search.quick');
    Route::get('/search/suggest', SearchSuggestionController::class)->name('search.suggest');
    Route::get('/settings', Livewire\Settings::class)->name('settings');
    Route::get('/notifications', Livewire\Notifications::class)->name('notifications.index');
    Route::post('/prefs/theme', function (Request $request) {
        $request->user()->update(['theme' => $request->input('theme') === 'dark' ? 'dark' : 'light']);

        return response()->noContent();
    })->name('prefs.theme');

    Route::middleware('perm:rfqs,V')->group(function () {
        Route::get('/rfqs', Livewire\Rfqs\Index::class)->name('rfqs.index');
        Route::get('/rfqs/create', Livewire\Rfqs\Form::class)->middleware('perm:rfqs,C')->name('rfqs.create');
        Route::get('/rfqs/{number}', Livewire\Rfqs\Show::class)->name('rfqs.show');
        Route::get('/rfqs/{number}/edit', Livewire\Rfqs\Form::class)->middleware('perm:rfqs,E')->name('rfqs.edit');
    });

    Route::middleware('perm:quotes,V')->group(function () {
        Route::get('/compare', Livewire\Rfqs\CompareIndex::class)->name('compare.index');
        Route::get('/rfqs/{number}/compare', Livewire\Rfqs\Compare::class)->name('rfqs.compare');
        Route::get('/quotes', Livewire\Quotes\Invitations::class)->name('quotes.invitations');
        Route::get('/quotes/{number}', Livewire\Quotes\Form::class)->name('quotes.form');
    });

    Route::middleware('perm:orders,V')->group(function () {
        Route::get('/orders', Livewire\Orders\Index::class)->name('orders.index');
        Route::get('/orders/{number}', Livewire\Orders\Show::class)->name('orders.show');
    });

    Route::get('/suppliers', Livewire\Suppliers\Index::class)->middleware('perm:suppliers,V')->name('suppliers.index');
    Route::get('/categories', Livewire\Categories\Index::class)->middleware('perm:catalog,V')->name('categories.index');
    Route::get('/users', Livewire\Users\Index::class)->middleware('perm:users,V')->name('users.index');
    Route::get('/logs', Livewire\Logs::class)->name('logs.index');

    // ---- Part 2 ----
    Route::middleware('perm:requisitions,V')->group(function () {
        Route::get('/requisitions', Livewire\Requisitions\Index::class)->name('requisitions.index');
        Route::get('/requisitions/create', Livewire\Requisitions\Form::class)->middleware('perm:requisitions,C')->name('requisitions.create');
        Route::get('/requisitions/{number}', Livewire\Requisitions\Show::class)->name('requisitions.show');
        Route::get('/requisitions/{number}/edit', Livewire\Requisitions\Form::class)->middleware('perm:requisitions,E')->name('requisitions.edit');
    });
    Route::middleware('perm:contracts,V')->group(function () {
        Route::get('/contracts', Livewire\Contracts\Index::class)->name('contracts.index');
        Route::get('/contracts/create', Livewire\Contracts\Form::class)->middleware('perm:contracts,C')->name('contracts.create');
        Route::get('/contracts/{number}', Livewire\Contracts\Show::class)->name('contracts.show');
        Route::get('/contracts/{number}/edit', Livewire\Contracts\Form::class)->middleware('perm:contracts,E')->name('contracts.edit');
    });
    Route::middleware('perm:invoices,V')->group(function () {
        Route::get('/invoices', Livewire\Invoices\Index::class)->name('invoices.index');
        Route::get('/invoices/{number}', Livewire\Invoices\Show::class)->name('invoices.show');
    });
    Route::middleware('perm:field,V')->group(function () {
        Route::get('/assessments', Livewire\Assessments\Index::class)->name('assessments.index');
        Route::get('/assessments/{number}', Livewire\Assessments\Show::class)->name('assessments.show');
    });
    Route::get('/rfis', Livewire\Rfis\Index::class)->middleware('perm:rfqs,V')->name('rfis.index');
    Route::get('/rfis/create', Livewire\Rfis\Form::class)->middleware('perm:rfqs,C')->name('rfis.create');
    Route::get('/rfis/{number}', Livewire\Rfis\Show::class)->middleware('perm:rfqs,V')->name('rfis.show');
    Route::get('/rfis/{number}/edit', Livewire\Rfis\Form::class)->middleware('perm:rfqs,E')->name('rfis.edit');
    Route::get('/rfqs/{number}/auction', Livewire\Rfqs\Auction::class)->middleware('perm:quotes,V')->name('rfqs.auction');
    Route::get('/messages', Livewire\Messages\Index::class)->name('messages.index');
    Route::get('/suppliers/{supplier}', Livewire\Suppliers\Show::class)->whereNumber('supplier')->name('suppliers.show');
    Route::get('/suppliers/{supplier}/documents/{document}', function (Request $request, int $supplier, int $document) {
        $u = $request->user();
        abort_unless(($u->isSourcing() && $u->allowed('suppliers')) || ($u->role === 'supplier' && $u->supplier_id === $supplier), 403);
        $doc = \App\Models\SupplierDocument::where('supplier_id', $supplier)->findOrFail($document);
        abort_unless($doc->path && \Illuminate\Support\Facades\Storage::disk('local')->exists($doc->path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->download($doc->path, $doc->original_name ?? 'document');
    })->whereNumber(['supplier', 'document'])->name('suppliers.document');

    // ---- Part 3 ----
    Route::post('/prefs/locale', function (Request $request) {
        $locale = in_array($request->input('locale'), array_keys(config('procurement.locales')), true) ? $request->input('locale') : 'en';
        $request->user()->update(['locale' => $locale]);

        return back();
    })->name('prefs.locale');
    Route::middleware('perm:marketplace,V')->group(function () {
        Route::get('/market', Livewire\Market\Shop::class)->name('market.shop');
        Route::get('/market/basket', Livewire\Market\Basket::class)->name('market.basket');
        Route::get('/market/sourcing/{number?}', Livewire\Market\Sourcing::class)->name('market.sourcing');
        Route::get('/market/orders', Livewire\Market\Orders::class)->name('market.orders.index');
        Route::get('/market/orders/{number}', Livewire\Market\OrderShow::class)->name('market.orders.show');
        Route::get('/market/orders/{number}/pdf', [\App\Http\Controllers\PdfController::class, 'marketOrder'])->name('market.orders.pdf');
        Route::get('/market/products', Livewire\Market\Offers::class)->name('market.offers');
        Route::get('/market/tasks', Livewire\Market\Tasks::class)->name('market.tasks');
    });
    Route::get('/markets', Livewire\Market\Admin::class)->middleware('perm:catalog,A')->name('markets.index');
    Route::get('/spend', Livewire\Reports::class)->middleware('perm:spend,V')->defaults('page', 'spend')->name('spend.index');
    Route::get('/savings', Livewire\Reports::class)->middleware('perm:spend,V')->defaults('page', 'savings')->name('savings.index');
    Route::get('/kpi-reports', Livewire\Reports::class)->middleware('perm:reports,V')->defaults('page', 'kpis')->name('kpi.index');
    Route::get('/reports', fn (Request $request) => redirect()->route(['savings' => 'savings.index', 'kpis' => 'kpi.index'][$request->query('tab')] ?? 'spend.index', $request->query('tab') === 'budgets' ? ['tab' => 'budgets'] : []))->middleware('perm:spend,V')->name('reports.index');
    Route::get('/accounting', Livewire\Accounting::class)->middleware('perm:invoices,A')->name('accounting.index');
    Route::get('/outbox', Livewire\Outbox::class)->middleware('perm:system,V')->name('outbox.index');
    Route::get('/orders/{number}/pdf', [\App\Http\Controllers\PdfController::class, 'order'])->middleware('perm:orders,V')->name('orders.pdf');
    Route::get('/rfqs/{number}/pdf', [\App\Http\Controllers\PdfController::class, 'rfq'])->middleware('perm:rfqs,V')->name('rfqs.pdf');
    Route::get('/invoices/{number}/pdf', [\App\Http\Controllers\PdfController::class, 'invoice'])->middleware('perm:invoices,V')->name('invoices.pdf');

    // ---- Part 4 ----
    Route::view('/privacy', 'pages.privacy')->name('privacy');
    Route::get('/rfqs/{number}/scenarios', Livewire\Rfqs\Scenarios::class)->middleware('perm:quotes,V')->name('rfqs.scenarios');
    Route::get('/monitoring', Livewire\Monitoring::class)->middleware('perm:reports,V')->defaults('page', 'monitoring')->name('monitoring.index');
    Route::get('/sla', Livewire\Monitoring::class)->middleware('perm:reports,V')->defaults('page', 'sla')->name('sla.index');
    Route::get('/supplier-360', Livewire\Suppliers\Insight::class)->middleware('perm:suppliers,V')->name('sup360.index');
    Route::get('/system/health', Livewire\System::class)->middleware('perm:system,V')->defaults('page', 'health')->name('health.index');
    Route::get('/system/flags', Livewire\System::class)->middleware('perm:system,V')->defaults('page', 'flags')->name('flags.index');
    Route::get('/import-export', Livewire\System::class)->middleware('perm:system,V')->defaults('page', 'impex')->name('impex.index');
    Route::get('/system', fn (Request $request) => redirect()->route(['flags' => 'flags.index', 'data' => 'impex.index'][$request->query('tab')] ?? 'health.index', $request->query('tab') === 'currencies' ? ['tab' => 'currencies'] : []))->middleware('perm:system,V')->name('system.index');
    Route::get('/assistant', Livewire\Assistant::class)->middleware('perm:rfqs,V')->name('assistant.index');
    Route::get('/workers', Livewire\Workers::class)->name('workers.index');
    Route::get('/files/{attachment}', function (Request $request, \App\Models\Attachment $attachment) {
        abort_unless(app(\App\Services\AttachmentService::class)->canSee($attachment, $request->user()), 404);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($attachment->path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->download($attachment->path, $attachment->name);
    })->whereNumber('attachment')->name('files.show');
});
