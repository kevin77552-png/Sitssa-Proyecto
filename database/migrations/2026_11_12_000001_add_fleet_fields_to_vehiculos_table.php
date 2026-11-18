<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->string('codigo_interno', 30)->nullable()->unique();
            $table->string('tipo', 40)->nullable();
            $table->string('modelo', 60)->nullable();
            $table->string('estado', 10)->default('activa')->index();
            $table->string('external_id', 64)->nullable()->unique();
            $table->string('source', 20)->default('manual');
            $table->timestamp('last_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropColumn([
                'codigo_interno',
                'tipo',
                'modelo',
                'estado',
                'external_id',
                'source',
                'last_synced_at',
            ]);
        });
    }
};
