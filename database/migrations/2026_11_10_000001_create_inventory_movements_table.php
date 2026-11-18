<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->integer('delta');
            $table->integer('stock_antes');
            $table->integer('stock_despues');
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->string('vehiculo_placa', 20)->nullable();
            $table->string('vehiculo_descripcion', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('motivo')->nullable();
            $table->string('referencia_tipo', 40)->nullable();
            $table->string('referencia_id', 64)->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['tipo', 'occurred_at']);
            $table->index(['vehiculo_id', 'occurred_at']);
            $table->index(['inventory_item_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
