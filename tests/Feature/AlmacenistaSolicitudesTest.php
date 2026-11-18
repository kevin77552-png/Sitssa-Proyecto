<?php

namespace Tests\Feature;

use App\Livewire\Almacenista\Solicitudes as SolicitudesComponent;
use App\Models\SpareRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AlmacenistaSolicitudesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function boton_confirmar_no_aparece_antes_de_aprobacion_y_no_se_puede_ejecutar()
    {
        $user = User::factory()->create(['role' => 'almacenista']);
        $this->actingAs($user);

        $req = SpareRequest::create([
            'cantidad' => 1,
            'descripcion' => 'Prueba',
            'almacenista_id' => $user->id,
            'status' => 'pending',
        ]);

        Livewire::actingAs($user)
            ->test(SolicitudesComponent::class)
            ->assertDontSee('Confirmar recibido')
            ->call('confirmReceived', $req->id)
            ->assertDispatched('notification', type: 'info', message: 'No se puede confirmar hasta que la solicitud sea aprobada.');

        $this->assertDatabaseHas('spare_requests', [
            'id' => $req->id,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function boton_confirmar_aparece_despues_de_aprobacion_y_modifica_estado()
    {
        $user = User::factory()->create(['role' => 'almacenista']);
        $this->actingAs($user);

        $req = SpareRequest::create([
            'cantidad' => 1,
            'descripcion' => 'Prueba 2',
            'almacenista_id' => $user->id,
            'status' => 'approved',
        ]);

        Livewire::actingAs($user)
            ->test(SolicitudesComponent::class)
            ->assertSee('Confirmar recibido')
            ->call('confirmReceived', $req->id)
            ->assertDispatched('notification', type: 'success', message: 'Recibió correctamente el repuesto.');

        $this->assertDatabaseHas('spare_requests', [
            'id' => $req->id,
            'status' => 'received',
        ]);
    }

    public function test_filters_show_only_owned_requests_and_actual_counts(): void
    {
        $user = User::factory()->create(['role' => 'almacenista']);
        $otherUser = User::factory()->create(['role' => 'almacenista']);

        foreach ([
            [$user->id, 'Pendiente propio', 'pending'],
            [$user->id, 'Aprobada propia', 'approved'],
            [$user->id, 'Recibida propia', 'received'],
            [$otherUser->id, 'Solicitud ajena', 'pending'],
        ] as [$ownerId, $description, $status]) {
            SpareRequest::create([
                'cantidad' => 1,
                'descripcion' => $description,
                'almacenista_id' => $ownerId,
                'status' => $status,
            ]);
        }

        Livewire::actingAs($user)
            ->test(SolicitudesComponent::class)
            ->assertSee('Todas (3)')
            ->assertSee('Aprobadas / por recibir (1)')
            ->assertSee('En revisión (1)')
            ->assertSee('Recibidas (1)')
            ->assertSee('Pendiente propio')
            ->assertDontSee('Solicitud ajena')
            ->set('statusFilter', 'pending')
            ->assertSee('Pendiente propio')
            ->assertDontSee('Aprobada propia')
            ->assertDontSee('Recibida propia');
    }

    public function test_new_request_button_opens_the_existing_form(): void
    {
        $user = User::factory()->create(['role' => 'almacenista']);

        Livewire::actingAs($user)
            ->test(SolicitudesComponent::class)
            ->call('newRequest')
            ->assertDispatched('showModule', 'almacenista.solicitar_repuestos');
    }
}
