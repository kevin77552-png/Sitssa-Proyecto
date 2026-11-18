<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedBigInteger('excel_row');
            $table->string('status', 24)->index();
            $table->string('sigicov')->nullable();
            $table->text('reason')->nullable();
            $table->json('original_values')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'excel_row']);
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
