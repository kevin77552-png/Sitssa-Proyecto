<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_INDEX = 'inventory_items_sigicov_unique';

    public function up(): void
    {
        if (! Schema::hasTable('inventory_items')) {
            return;
        }

        foreach (Schema::getIndexes('inventory_items') as $index) {
            if (
                ($index['name'] ?? null) === self::LEGACY_INDEX
                && ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['sigicov']
            ) {
                Schema::table('inventory_items', function (Blueprint $table): void {
                    $table->dropUnique(self::LEGACY_INDEX);
                });

                return;
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_items')) {
            return;
        }

        foreach (Schema::getIndexes('inventory_items') as $index) {
            if (($index['name'] ?? null) === self::LEGACY_INDEX) {
                return;
            }
        }

        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->unique('sigicov', self::LEGACY_INDEX);
        });
    }
};
