<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('keywords')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('inventory_type_id')
                ->nullable()
                ->after('descripcion')
                ->constrained('inventory_types')
                ->nullOnDelete();
            $table->index('inventory_type_id');
        });

        Schema::table('import_rows', function (Blueprint $table) {
            $table->foreignId('inventory_type_id')
                ->nullable()
                ->after('sigicov')
                ->constrained('inventory_types')
                ->nullOnDelete();
            $table->foreignId('suggested_inventory_type_id')
                ->nullable()
                ->after('inventory_type_id')
                ->constrained('inventory_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suggested_inventory_type_id');
            $table->dropConstrainedForeignId('inventory_type_id');
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_type_id');
        });

        Schema::dropIfExists('inventory_types');
    }
};
