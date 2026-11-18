<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batch_seen_sigicovs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->char('sigicov_key_hash', 64);
            $table->unique(['batch_id', 'sigicov_key_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batch_seen_sigicovs');
    }
};
