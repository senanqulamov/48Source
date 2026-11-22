<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('market_worker_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->constrained('markets')->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('worker_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 160);
            $table->text('details')->nullable();
            $table->string('type', 32);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['worker_id', 'completed_at', 'due_at'], 'market_tasks_worker_due_idx');
            $table->index(['seller_id', 'market_id'], 'market_tasks_seller_market_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_worker_tasks');
    }
};
