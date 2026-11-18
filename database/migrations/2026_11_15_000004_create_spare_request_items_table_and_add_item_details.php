<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->text('detalles')->nullable();
        });

        Schema::create('spare_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spare_request_id')->constrained()->cascadeOnDelete();
            $table->text('descripcion');
            $table->unsignedInteger('cantidad');
            $table->text('detalles')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spare_request_items');

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('detalles');
        });
    }
};
