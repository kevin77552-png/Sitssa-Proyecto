<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_hash', 64)->index();
            $table->unsignedBigInteger('file_size');
            $table->string('status', 24)->index();
            $table->string('sheet_name')->nullable();
            $table->string('requested_sheet')->nullable();
            $table->date('cutoff_date')->nullable();
            $table->unsignedBigInteger('rows_read')->default(0);
            $table->unsignedBigInteger('new_rows')->default(0);
            $table->unsignedBigInteger('existing_rows')->default(0);
            $table->unsignedBigInteger('duplicate_rows')->default(0);
            $table->unsignedBigInteger('invalid_rows')->default(0);
            $table->unsignedBigInteger('imported_rows')->default(0);
            $table->unsignedBigInteger('records_before')->nullable();
            $table->unsignedBigInteger('records_after')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
