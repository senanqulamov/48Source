<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('procurement_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->unique()->constrained('requests')->restrictOnDelete();
            $table->foreignId('quote_id')->unique()->constrained('quotes')->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('awarded_by')->constrained('users')->restrictOnDelete();
            $table->text('award_justification')->nullable();
            $table->char('currency', 3);
            $table->decimal('total_amount', 16, 2);
            $table->string('status', 32)->default('draft');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('in_transit_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('procurement_purchase_orders')->cascadeOnDelete();
            $table->foreignId('request_item_id')->constrained('request_items')->restrictOnDelete();
            $table->string('description');
            $table->decimal('quantity', 16, 2);
            $table->decimal('unit_price', 16, 2);
            $table->decimal('tax_rate', 7, 2)->default(0);
            $table->decimal('line_total', 16, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_purchase_order_lines');
        Schema::dropIfExists('procurement_purchase_orders');
    }
};
