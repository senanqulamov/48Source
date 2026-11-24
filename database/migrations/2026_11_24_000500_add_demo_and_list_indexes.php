<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_data_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('seed');
            $table->unsignedInteger('scale');
            $table->string('password_hint');
            $table->timestamps();
        });

        Schema::table('rfqs', function (Blueprint $table) {
            $table->index(['buyer_id', 'status', 'created_at'], 'rfqs_buyer_status_created_index');
            $table->index(['category_id', 'status', 'created_at'], 'rfqs_category_status_created_index');
        });
        Schema::table('purchase_orders', fn (Blueprint $table) => $table->index(['buyer_id', 'status', 'created_at'], 'po_buyer_status_created_index'));
        Schema::table('invoices', fn (Blueprint $table) => $table->index(['supplier_id', 'status', 'due_on'], 'invoices_supplier_status_due_index'));
        Schema::table('market_orders', fn (Blueprint $table) => $table->index(['buyer_id', 'status', 'created_at'], 'market_orders_buyer_status_created_index'));
        Schema::table('offers', fn (Blueprint $table) => $table->index(['store_id', 'status', 'product_id'], 'offers_store_status_product_index'));
    }

    public function down(): void
    {
        Schema::table('offers', fn (Blueprint $table) => $table->dropIndex('offers_store_status_product_index'));
        Schema::table('market_orders', fn (Blueprint $table) => $table->dropIndex('market_orders_buyer_status_created_index'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropIndex('invoices_supplier_status_due_index'));
        Schema::table('purchase_orders', fn (Blueprint $table) => $table->dropIndex('po_buyer_status_created_index'));
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropIndex('rfqs_buyer_status_created_index');
            $table->dropIndex('rfqs_category_status_created_index');
        });
        Schema::dropIfExists('demo_data_runs');
    }
};
