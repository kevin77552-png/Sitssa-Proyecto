<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.register');
    }

    public function test_new_users_can_register(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'Password!123')
            ->set('password_confirmation', 'Password!123')
            ->set('role', 'superadministrador');

        $component->call('register');

        $component->assertHasNoErrors()
            ->assertSee('Usuario creado correctamente');
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => 'almacenista',
        ]);
        $this->assertGuest();
    }

    public function test_success_alert_is_reset_for_consecutive_user_registrations(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrador']));

        $component = Volt::test('pages.auth.register')
            ->set('name', 'First User')
            ->set('email', 'first@example.com')
            ->set('password', 'Password!123')
            ->set('password_confirmation', 'Password!123')
            ->set('role', 'gerente')
            ->call('register')
            ->assertHasNoErrors()
            ->assertSet('success', true)
            ->assertSee('wire:key="registration-success-1"', false);

        $component
            ->set('name', 'Second User')
            ->set('email', 'second@example.com')
            ->set('password', 'Password!123')
            ->set('password_confirmation', 'Password!123')
            ->set('role', 'gerente')
            ->call('register')
            ->assertHasNoErrors()
            ->assertSet('success', true)
            ->assertSee('Usuario creado correctamente')
            ->assertSee('wire:key="registration-success-2"', false);

        $this->assertDatabaseHas('users', ['email' => 'first@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'second@example.com']);
    }

    public function test_administrator_sees_only_roles_they_can_assign(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrador']));

        Volt::test('pages.auth.register')
            ->assertSee('Almacenista')
            ->assertSee('Gerente')
            ->assertSee('Proveedor')
            ->assertDontSee('Administrador')
            ->assertDontSee('Superadministrador');
    }

    public function test_administrator_cannot_register_another_administrator(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrador']));

        $component = Volt::test('pages.auth.register')
            ->set('name', 'Restricted User')
            ->set('email', 'restricted@example.com')
            ->set('password', 'Password!123')
            ->set('password_confirmation', 'Password!123')
            ->set('role', 'administrador')
            ->call('register')
            ->assertHasErrors(['role']);

        $this->assertDatabaseMissing('users', ['email' => 'restricted@example.com']);

        $component->set('role', 'gerente')
            ->call('register')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'restricted@example.com',
            'role' => 'gerente',
        ]);
    }

    public function test_superadministrator_sees_elevated_roles(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superadministrador']));

        Volt::test('pages.auth.register')
            ->assertSee('Administrador')
            ->assertSee('Superadministrador');
    }
}
