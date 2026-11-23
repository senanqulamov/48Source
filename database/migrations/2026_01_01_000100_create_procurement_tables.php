<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money is stored as integer minor units (cents) and quantities as integer
 * thousandths, so no financial value ever passes through a float.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $t) {
            $t->id();
            $t->string('name', 20);
            $t->unsignedSmallInteger('year');
            $t->unsignedInteger('value')->default(0);
            $t->unique(['name', 'year']);
        });

        Schema::create('rfqs', function (Blueprint $t) {
            $t->id();
            $t->string('number', 20)->unique();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('type', 20)->default('invited'); // invited, public
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('buyer_id')->constrained('users');
            $t->string('status', 20)->default('draft')->index(); // draft, open, closed, awarded, cancelled
            $t->string('currency', 3)->default('USD');
            $t->bigInteger('budget_cents')->nullable();
            $t->dateTime('deadline_at')->nullable()->index();
            $t->string('delivery_location')->nullable();
            $t->date('required_by')->nullable();
            $t->text('requirements')->nullable();
            $t->string('payment_terms')->nullable();
            $t->string('scoring_preset', 20)->default('balanced');
            $t->boolean('sealed')->default(false);
            $t->unsignedSmallInteger('round')->default(1);
            $t->timestamp('published_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamp('awarded_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('rfq_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('position')->default(1);
            $t->string('name');
            $t->string('description')->nullable();
            $t->bigInteger('quantity_milli');
            $t->string('unit', 20)->default('pcs');
            $t->bigInteger('target_price_cents')->nullable();
            $t->timestamps();
        });

        Schema::create('rfq_invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $t->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $t->string('status', 20)->default('pending'); // pending, accepted, declined
            $t->string('decline_reason')->nullable();
            $t->timestamp('responded_at')->nullable();
            $t->timestamps();
            $t->unique(['rfq_id', 'supplier_id']);
        });

        Schema::create('quotes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $t->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 20)->default('draft')->index(); // draft, submitted, withdrawn, won, lost
            $t->string('currency', 3)->default('USD');
            $t->unsignedSmallInteger('delivery_days')->nullable();
            $t->string('payment_terms')->nullable();
            $t->date('valid_until')->nullable();
            $t->text('notes')->nullable();
            $t->bigInteger('total_cents')->default(0);
            $t->unsignedSmallInteger('revision')->default(0);
            $t->timestamp('submitted_at')->nullable();
            $t->timestamps();
            $t->unique(['rfq_id', 'supplier_id']);
        });

        Schema::create('quote_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $t->foreignId('rfq_item_id')->constrained()->cascadeOnDelete();
            $t->bigInteger('unit_price_cents')->nullable();
            $t->bigInteger('total_cents')->default(0);
            $t->timestamps();
            $t->unique(['quote_id', 'rfq_item_id']);
        });

        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->string('number', 20)->unique();
            $t->foreignId('rfq_id')->constrained();
            $t->foreignId('supplier_id')->constrained();
            $t->foreignId('quote_id')->constrained();
            $t->foreignId('buyer_id')->constrained('users');
            $t->string('status', 20)->default('draft')->index(); // draft, issued, acknowledged, in_transit, delivered, invoiced, cancelled
            $t->string('currency', 3)->default('USD');
            $t->bigInteger('total_cents')->default(0);
            $t->string('payment_terms')->nullable();
            $t->string('delivery_location')->nullable();
            $t->date('expected_at')->nullable();
            $t->text('award_justification')->nullable();
            $t->timestamp('issued_at')->nullable();
            $t->timestamp('acknowledged_at')->nullable();
            $t->timestamp('shipped_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('invoiced_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('rfq_item_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->bigInteger('quantity_milli');
            $t->string('unit', 20);
            $t->bigInteger('unit_price_cents');
            $t->bigInteger('total_cents');
            $t->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('actor_name');
            $t->string('area', 30)->index();
            $t->string('action');
            $t->nullableMorphs('subject');
            $t->string('subject_label')->nullable();
            $t->foreignId('supplier_id')->nullable()->index();
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('role_permissions', function (Blueprint $t) {
            $t->id();
            $t->string('role', 30);
            $t->string('module', 30);
            $t->string('abilities', 4)->default(''); // any of V C E A
            $t->timestamps();
            $t->unique(['role', 'module']);
        });

        Schema::create('feature_flags', function (Blueprint $t) {
            $t->id();
            $t->string('key', 40)->unique();
            $t->string('label');
            $t->boolean('enabled')->default(false);
            $t->timestamps();
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'feature_flags', 'role_permissions', 'activity_logs', 'purchase_order_items', 'purchase_orders', 'quote_items', 'quotes', 'rfq_invitations', 'rfq_items', 'rfqs', 'sequences'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
