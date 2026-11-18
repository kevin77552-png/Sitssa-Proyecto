<?php

namespace Tests\Feature;

use App\Livewire\Admin\Reports;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\SpareRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ReportsScalingTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_counts_all_pending_records_but_loads_only_recent_details(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);
        $warehouseUser = User::factory()->create(['role' => 'almacenista']);

        for ($index = 0; $index < 11; $index++) {
            $date = now()->subMinutes(11 - $index);
            $request = SpareRequest::create([
                'almacenista_id' => $warehouseUser->id,
                'cantidad' => 1,
                'descripcion' => $index === 0 ? 'Pendiente antiguo' : "Pendiente reciente {$index}",
                'status' => 'pending',
            ]);
            $request->forceFill(['created_at' => $date, 'updated_at' => $date])->save();

            $order = PurchaseOrder::create([
                'cantidad' => 1,
                'descripcion' => "Orden reciente {$index}",
                'status' => 'aprobado',
            ]);
            $order->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
        }

        Livewire::actingAs($administrator)
            ->test(Reports::class)
            ->assertViewHas('pendingRequestCount', 11)
            ->assertViewHas('pendingRequests', fn ($rows) => $rows->count() === 10)
            ->assertViewHas('openOrderCount', 11)
            ->assertViewHas('openOrdersMissingInvoice', fn ($rows) => $rows->count() === 10)
            ->assertSee('Pendiente reciente 10')
            ->assertDontSee('Pendiente antiguo');
    }

    public function test_report_approval_average_is_aggregated_by_the_database(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);
        $approvedAt = Carbon::parse('2026-01-01 12:00:00');

        foreach ([11, 7] as $createdHour) {
            $order = PurchaseOrder::create([
                'cantidad' => 1,
                'descripcion' => 'Orden con factura',
                'status' => 'factura_subida',
                'approved_at' => $approvedAt,
            ]);
            $createdAt = Carbon::parse("2026-01-01 {$createdHour}:00:00");
            $order->forceFill(['created_at' => $createdAt, 'updated_at' => $approvedAt])->save();
        }

        Livewire::actingAs($administrator)
            ->test(Reports::class)
            ->assertViewHas('avgApprovalHours', 3.0);
    }

    public function test_stock_alerts_are_paginated_in_the_view_and_streamed_in_full_on_export(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);
        Schema::table('inventory_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_items', 'stock_minimo')) {
                $table->string('stock_minimo')->nullable();
            }
            if (! Schema::hasColumn('inventory_items', 'reorder_point')) {
                $table->string('reorder_point')->nullable();
            }
        });

        for ($index = 0; $index < 12; $index++) {
            $critical = InventoryItem::create([
                'sigicov' => '93000'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'descripcion' => "Crítico {$index}",
                'inventario' => '1',
            ]);
            DB::table('inventory_items')->where('id', $critical->id)->update([
                'stock_minimo' => '2',
                'reorder_point' => '3',
            ]);

            $low = InventoryItem::create([
                'sigicov' => '94000'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'descripcion' => "Bajo {$index}",
                'inventario' => '2',
            ]);
            DB::table('inventory_items')->where('id', $low->id)->update([
                'stock_minimo' => '1',
                'reorder_point' => '3',
            ]);
        }

        Livewire::actingAs($administrator)
            ->test(Reports::class)
            ->assertViewHas('criticalStockCount', 12)
            ->assertViewHas('criticalStock', fn ($rows) => $rows->count() === 10 && $rows->total() === 12)
            ->assertViewHas('lowStockCount', 12)
            ->assertViewHas('lowStock', fn ($rows) => $rows->count() === 10 && $rows->total() === 12);

        $component = Livewire::actingAs($administrator)->test(Reports::class)->instance();
        $response = $component->export();
        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        try {
            $response->sendContent();
            $csv = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $this->assertSame(12, substr_count($csv, 'Crítico:'));
        $this->assertSame(12, substr_count($csv, 'Bajo:'));
    }
}
