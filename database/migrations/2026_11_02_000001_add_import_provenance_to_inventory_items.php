<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('origen')->nullable()->after('resp_accesorios');
            $table->string('lote_importacion')->nullable()->after('origen');
            $table->index(['origen', 'lote_importacion']);
        });

    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndex(['origen', 'lote_importacion']);
            $table->dropColumn(['origen', 'lote_importacion']);
        });
    }
};
