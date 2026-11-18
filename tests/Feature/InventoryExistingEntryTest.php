<?php

namespace Tests\Feature;

use App\Livewire\Admin\InventoryIngreso;
use App\Livewire\Almacenista\Solicitudes;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\SpareRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryExistingEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingreso_no_muestra_entrada_existente_ni_columnas_de_stock_y_aclara_fecha_de_registro(): void
    {
        $user = User::factory()->create(['role' => 'almacenista']);

        Livewire::actingAs($user)
            ->test(InventoryIngreso::class)
            ->assertDontSee('Registrar entrada a repuesto existente')
            ->assertDontSee('Cantidad disponible')
            ->assertDontSee('N° de inventario')
            ->assertDontSee('TS')
            ->assertSee('Fecha de registro');
    }

    public function test_confirm_received_remains_separate_from_stock_replenishment(): void
    {
        $user = User::factory()->create(['role' => 'almacenista']);
        $item = $this->createItem('12345', '10');
        $request = SpareRequest::unguarded(fn () => SpareRequest::create([
            'almacenista_id' => $user->id,
            'cantidad' => 4,
            'descripcion' => 'Repuesto existente',
            'tipo' => 'compra',
            'inventory_item_id' => $item->id,
            'status' => 'approved',
        ]));

        Livewire::actingAs($user)
            ->test(Solicitudes::class)
            ->call('confirmReceived', $request->id)
            ->assertDispatched('notification', type: 'success', message: 'Recibió correctamente el repuesto.');

        $this->assertSame('10', $item->fresh()->inventario);
        $this->assertSame('received', $request->fresh()->status);
        $this->assertSame(0, InventoryMovement::count());
    }

    private function createItem(string $sigicov, string $stock): InventoryItem
    {
        return InventoryItem::create([
            'sigicov' => $sigicov,
            'descripcion' => 'Repuesto existente',
            'inventario' => $stock,
        ]);
    }
}
