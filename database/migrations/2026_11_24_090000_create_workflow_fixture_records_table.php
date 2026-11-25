<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_fixture_records', function (Blueprint $table): void {
            $table->id();
            $table->string('batch', 40);
            $table->string('table_name', 80);
            $table->unsignedBigInteger('record_id');
            $table->timestamp('created_at');
            $table->unique(['batch', 'table_name', 'record_id'], 'workflow_fixture_record_unique');
            $table->index(['batch', 'table_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_fixture_records');
    }
};