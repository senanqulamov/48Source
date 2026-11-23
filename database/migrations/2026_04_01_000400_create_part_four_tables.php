<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Part 4: attachments, price adjustments, request reminders, currencies, sign-in codes, email preference, contract from an order. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $t) {
            $t->id();
            $t->morphs('attachable');
            $t->foreignId('uploaded_by')->constrained('users');
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->string('path');
            $t->string('name');
            $t->unsignedBigInteger('size')->default(0);
            $t->timestamps();
        });
        Schema::create('currencies', function (Blueprint $t) {
            $t->string('code', 3)->primary();
            $t->string('name');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::table('quotes', function (Blueprint $t) {
            $t->bigInteger('original_total_cents')->nullable();
            $t->text('adjust_reason')->nullable();
            $t->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('adjusted_at')->nullable();
        });
        Schema::table('rfqs', function (Blueprint $t) {
            $t->timestamp('last_reminded_at')->nullable();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('two_factor')->default(false);
            $t->boolean('email_notices')->default(true);
        });
        Schema::table('contracts', function (Blueprint $t) {
            $t->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
        });
        $now = now();
        foreach (['USD' => 'US dollar', 'EUR' => 'Euro', 'AZN' => 'Azerbaijani manat'] as $code => $name) {
            \Illuminate\Support\Facades\DB::table('currencies')->insert(['code' => $code, 'name' => $name, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('contracts', fn (Blueprint $t) => $t->dropConstrainedForeignId('purchase_order_id'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['two_factor', 'email_notices']));
        Schema::table('rfqs', fn (Blueprint $t) => $t->dropColumn('last_reminded_at'));
        Schema::table('quotes', function (Blueprint $t) {
            $t->dropConstrainedForeignId('adjusted_by');
            $t->dropColumn(['original_total_cents', 'adjust_reason', 'adjusted_at']);
        });
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('attachments');
    }
};
