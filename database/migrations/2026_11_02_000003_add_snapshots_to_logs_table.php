<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->string('actor_name')->nullable();
            $table->string('actor_role')->nullable();
            $table->text('entidad_resumen')->nullable();
            $table->json('cambios')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->dropColumn(['actor_name', 'actor_role', 'entidad_resumen', 'cambios']);
        });
    }
};
