<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rfq_worker_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('rfq_worker_assignments')->cascadeOnDelete();
            $table->string('label', 255);
            $table->string('result', 12)->nullable();
            $table->timestamps();
            $table->unique(['assignment_id', 'label'], 'worker_checklist_unique_label');
        });

        Schema::create('rfq_worker_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('rfq_worker_assignments')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['assignment_id', 'created_at']);
        });

        Schema::create('rfq_worker_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('rfq_worker_assignments')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('users')->restrictOnDelete();
            $table->string('storage_path', 500);
            $table->string('original_name', 255);
            $table->unsignedBigInteger('byte_size');
            $table->string('mime_type', 100);
            $table->timestamps();
            $table->index(['assignment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_worker_evidence');
        Schema::dropIfExists('rfq_worker_notes');
        Schema::dropIfExists('rfq_worker_checklist_items');
    }
};
