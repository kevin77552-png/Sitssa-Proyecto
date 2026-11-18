<?php

namespace Tests\Feature;

use App\Livewire\Admin\Settings;
use App\Livewire\DynamicModule;
use App\Livewire\Sidebar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_see_general_settings_menu_and_read_only_setting(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($administrator)
            ->test(Sidebar::class)
            ->assertSee('Configuración general')
            ->assertDontSee('Registro');

        Livewire::actingAs($administrator)
            ->test(Settings::class)
            ->assertSee('Administradores activos')
            ->assertSee('Solo un superadministrador puede modificar este parámetro.')
            ->assertDontSee('Administrar este límite');

        Livewire::actingAs($administrator)
            ->test(DynamicModule::class)
            ->call('showModule', 'admin.settings')
            ->assertSee('Configuración general')
            ->assertSee('Límite configurado');
    }

    public function test_superadministrator_can_open_registration_settings_from_general_settings(): void
    {
        $superadministrator = User::factory()->create(['role' => 'superadministrador']);

        Livewire::actingAs($superadministrator)
            ->test(Sidebar::class)
            ->assertSee('Configuración general')
            ->assertSee('Registro');

        Livewire::actingAs($superadministrator)
            ->test(Settings::class)
            ->assertSee('Administrar este límite')
            ->call('openRegistrationSettings')
            ->assertDispatched('showModule', 'admin.settings.registro');
    }
}
