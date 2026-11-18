<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $seen = [];
        DB::table('inventory_items')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(500, function ($items) use (&$seen): void {
                foreach ($items as $item) {
                    $value = trim(mb_strtoupper((string) $item->sigicov, 'UTF-8'));
                    if ($value === '') {
                        continue;
                    }

                    $key = ctype_digit($value) ? ltrim($value, '0') : $value;
                    $key = $key === '' ? '0' : $key;
                    if (isset($seen[$key])) {
                        throw new RuntimeException(
                            "No se puede crear la restricción de SIGICOV: los registros activos {$seen[$key]} y {$item->id} comparten la clave normalizada {$key}."
                        );
                    }

                    $seen[$key] = $item->id;
                }
            });

        if (! Schema::hasColumn('inventory_items', 'sigicov_active_key')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->string('sigicov_active_key')->nullable();
            });
        }

        DB::table('inventory_items')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(500, function ($items): void {
                foreach ($items as $item) {
                    $value = trim(mb_strtoupper((string) $item->sigicov, 'UTF-8'));
                    if ($value === '') {
                        continue;
                    }

                    $key = ctype_digit($value) ? ltrim($value, '0') : $value;
                    $key = $key === '' ? '0' : $key;
                    DB::table('inventory_items')->where('id', $item->id)->update([
                        'sigicov_active_key' => $key,
                    ]);
                }
            });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->unique('sigicov_active_key');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropUnique(['sigicov_active_key']);
            $table->dropColumn('sigicov_active_key');
        });
    }
};
