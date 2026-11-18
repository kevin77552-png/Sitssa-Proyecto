<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignUuid('import_batch_id')
                ->nullable()
                ->after('lote_importacion')
                ->constrained('import_batches')
                ->nullOnDelete();
            $table->json('data')->nullable()->after('import_batch_id');
            $table->index('import_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndex(['import_batch_id']);
            $table->dropConstrainedForeignId('import_batch_id');
            $table->dropColumn('data');
        });
    }
};
