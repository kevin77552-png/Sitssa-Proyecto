<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_job_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->string('job_type', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['dispatched_at', 'claimed_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_job_outbox');
    }
};
