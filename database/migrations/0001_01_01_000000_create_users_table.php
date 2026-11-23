<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $t->string('name');
            $t->string('slug')->unique();
            $t->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('legal_name')->nullable();
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->string('city')->nullable();
            $t->string('country')->nullable();
            $t->string('contact_name')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->string('status', 20)->default('pending')->index(); // pending, active, inactive, blocked
            $t->string('status_reason')->nullable();
            $t->unsignedTinyInteger('rating_tenths')->default(40); // 0-50, 45 = 4.5 of 5
            $t->timestamp('approved_at')->nullable();
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->string('role', 30)->default('buyer')->index(); // admin, buyer, supplier, seller, market_worker, supplier_worker
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 20)->default('active')->index(); // active, invited, disabled
            $t->string('department')->nullable();
            $t->string('phone')->nullable();
            $t->string('theme', 10)->default('light');
            $t->timestamp('last_login_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('categories');
    }
};
