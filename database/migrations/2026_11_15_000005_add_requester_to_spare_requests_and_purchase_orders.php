<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spare_requests', function (Blueprint $table) {
            $table->string('solicitado_por')->nullable();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('solicitado_por')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('solicitado_por');
        });

        Schema::table('spare_requests', function (Blueprint $table) {
            $table->dropColumn('solicitado_por');
        });
    }
};
