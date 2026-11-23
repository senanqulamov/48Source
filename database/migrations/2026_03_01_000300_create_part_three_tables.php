<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Part 3: marketplace (markets, stores, products, offers, basket, orders, worker tasks), budgets, email outbox, reminders, language. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('markets', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('city')->nullable();
            $t->string('address')->nullable();
            $t->string('status', 20)->default('active'); // active, closed
            $t->timestamps();
        });
        Schema::create('stores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('market_id')->constrained();
            $t->string('name')->unique();
            $t->string('stall')->nullable();
            $t->string('phone')->nullable();
            $t->string('status', 20)->default('active'); // active, paused
            $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('store_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
            $t->string('locale', 5)->default('en')->after('theme');
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('sku', 40)->unique();
            $t->string('name');
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->string('unit', 20);
            $t->text('description')->nullable();
            $t->string('status', 20)->default('active'); // active, retired
            $t->timestamps();
        });
        Schema::create('offers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->bigInteger('price_cents');
            $t->string('currency', 3)->default('USD');
            $t->bigInteger('moq_milli')->default(1000);
            $t->bigInteger('stock_milli')->default(0);
            $t->unsignedSmallInteger('lead_days')->default(1);
            $t->string('status', 20)->default('active'); // active, paused
            $t->timestamps();
            $t->unique(['product_id', 'store_id']);
        });
        Schema::create('cart_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $t->bigInteger('quantity_milli');
            $t->foreignId('rfq_item_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'offer_id']);
        });
        Schema::create('market_orders', function (Blueprint $t) {
            $t->id();
            $t->string('number', 20)->unique();
            $t->foreignId('buyer_id')->constrained('users');
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete(); // the buying company, when the buyer is a supplier user
            $t->foreignId('store_id')->constrained();
            $t->foreignId('rfq_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 20)->default('placed')->index(); // placed, confirmed, ready, delivered, cancelled
            $t->string('currency', 3)->default('USD');
            $t->bigInteger('total_cents')->default(0);
            $t->string('deliver_to')->nullable();
            $t->string('note')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('ready_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
        });
        Schema::create('market_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('market_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('rfq_item_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('sku', 40);
            $t->string('unit', 20);
            $t->bigInteger('quantity_milli');
            $t->bigInteger('unit_price_cents');
            $t->bigInteger('total_cents');
            $t->timestamps();
        });
        Schema::create('worker_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('market_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('store_id')->constrained();
            $t->string('type', 20); // pick, deliver
            $t->string('status', 20)->default('open'); // open, done, cancelled
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('done_at')->nullable();
            $t->timestamps();
        });
        Schema::create('budgets', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('year');
            $t->foreignId('category_id')->constrained()->cascadeOnDelete();
            $t->bigInteger('amount_cents');
            $t->timestamps();
            $t->unique(['year', 'category_id']);
        });
        Schema::create('outbox_emails', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('to');
            $t->string('subject');
            $t->text('body');
            $t->string('url')->nullable();
            $t->string('area', 40)->default('General');
            $t->string('status', 20)->default('queued')->index(); // queued, sent, failed
            $t->string('error')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
        });
        Schema::create('reminders', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique(); // what was reminded and to whom, so nothing is sent twice
            $t->timestamps();
        });
        Schema::table('invoices', function (Blueprint $t) {
            $t->timestamp('exported_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('exported_at'));
        foreach (['reminders', 'outbox_emails', 'budgets', 'worker_tasks', 'market_order_items', 'market_orders', 'cart_items', 'offers', 'products'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('store_id');
            $t->dropColumn('locale');
        });
        Schema::dropIfExists('stores');
        Schema::dropIfExists('markets');
    }
};
