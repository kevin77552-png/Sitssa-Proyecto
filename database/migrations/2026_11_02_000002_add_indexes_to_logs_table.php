<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->index('created_at', 'logs_created_at_index');
            $table->index(['user_id', 'created_at'], 'logs_user_created_at_index');
            $table->index(['entidad', 'created_at'], 'logs_entidad_created_at_index');
            $table->index(['entidad', 'entidad_id'], 'logs_entidad_entidad_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->dropIndex('logs_created_at_index');
            $table->dropIndex('logs_user_created_at_index');
            $table->dropIndex('logs_entidad_created_at_index');
            $table->dropIndex('logs_entidad_entidad_id_index');
        });
    }
};
