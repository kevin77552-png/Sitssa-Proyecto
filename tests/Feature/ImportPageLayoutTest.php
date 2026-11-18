<?php

namespace Tests\Feature;

use App\Livewire\Sidebar;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ImportPageLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_page_does_not_render_dashboard_below_its_content(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);

        $this->withoutVite()
            ->actingAs($administrator)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertSee('Importaciones de inventario')
            ->assertDontSee('Buen día');
    }

    public function test_dashboard_still_renders_and_can_open_any_role_allowed_module_from_query(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);

        $this->withoutVite()
            ->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Buen día')
            ->assertSeeInOrder([
                'aria-label="SITSSA, ir al inicio"',
                'sittsa-logo-blanco.png',
                'sittsa-emblema-blanco.png',
                'Inicio',
            ])
            ->assertDontSee('ml-5 block h-10 w-10');

        $this->get(route('dashboard', ['module' => 'admin.profile']))
            ->assertOk()
            ->assertSee('Perfil de usuario')
            ->assertSee($administrator->name)
            ->assertSee($administrator->email)
            ->assertSee('Información personal')
            ->assertSee('Actualizar contraseña')
            ->assertDontSee('CUENTA')
            ->assertDontSee('usr_2306km')
            ->assertDontSee('Vista general')
            ->assertDontSee('Buen día');

        $this->get(route('dashboard', ['module' => 'admin.history']))
            ->assertOk()
            ->assertSee('Histórico general');
    }

    public function test_module_query_is_still_checked_against_the_current_role(): void
    {
        $provider = User::factory()->create(['role' => 'proveedor']);

        $this->withoutVite()
            ->actingAs($provider)
            ->get(route('dashboard', ['module' => 'admin.history']))
            ->assertForbidden();
    }

    public function test_sidebar_navigates_from_a_standalone_import_page_to_selected_dashboard_module(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);
        $batch = ImportBatch::create([
            'user_id' => $administrator->id,
            'file_name' => 'layout-test.xlsx',
            'file_path' => 'imports/layout-test.xlsx',
            'file_hash' => hash('sha256', 'layout-test'),
            'file_size' => 100,
            'status' => 'ready',
        ]);
        $this->withoutVite()
            ->actingAs($administrator)
            ->get(route('imports.show', $batch))
            ->assertOk()
            ->assertDontSee('Buen día');

        Livewire::actingAs($administrator)
            ->test(Sidebar::class, ['standalonePage' => true])
            ->assertSet('standalonePage', true)
            ->call('select', 'admin.history')
            ->assertRedirect(route('dashboard', ['module' => 'admin.history']));
    }
}
