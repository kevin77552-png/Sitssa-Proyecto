<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_hash', 64)->index();
            $table->unsignedBigInteger('file_size');
            $table->string('status', 32)->index();
            $table->string('phase', 16)->default('analyze');
            $table->string('sheet_name')->nullable();
            $table->date('cutoff_date')->nullable();
            $table->boolean('duplicate_file_warning')->default(false);
            $table->unsignedBigInteger('rows_read')->default(0);
            $table->unsignedBigInteger('new_rows')->default(0);
            $table->unsignedBigInteger('existing_rows')->default(0);
            $table->unsignedBigInteger('duplicate_rows')->default(0);
            $table->unsignedBigInteger('invalid_rows')->default(0);
            $table->unsignedBigInteger('imported_rows')->default(0);
            $table->unsignedBigInteger('records_before')->nullable();
            $table->unsignedBigInteger('records_after')->nullable();
            $table->string('backup_path')->nullable();
            $table->json('report')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('batch_id')->constrained('inventory_import_batches')->cascadeOnDelete();
            $table->unsignedBigInteger('row_number');
            $table->string('status', 24)->index();
            $table->string('sigicov')->nullable();
            $table->string('sigicov_key')->nullable();
            $table->unsignedInteger('apply_chunk')->nullable();
            $table->text('reason')->nullable();
            $table->json('original_values')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'row_number']);
            $table->index(['batch_id', 'status']);
            $table->index(['batch_id', 'sigicov_key']);
            $table->index(['batch_id', 'apply_chunk']);
        });

        Schema::create('inventory_import_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('batch_id')->constrained('inventory_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('chunk_number');
            $table->string('status', 16)->default('queued');
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'chunk_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_import_chunks');
        Schema::dropIfExists('inventory_import_rows');
        Schema::dropIfExists('inventory_import_batches');
    }
};
