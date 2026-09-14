<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Mockery;
use Tests\TestCase;

class SsoLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        config([
            'sso.enabled' => true,
            'sso.default_role' => 'consulta',
            'services.azure.client_id' => 'dummy-client',
            'services.azure.tenant' => 'dummy-tenant',
            'services.azure.redirect' => 'http://localhost/auth/microsoft/callback',
        ]);
    }

    private function fakeAzureUser(string $id, string $email, string $name = 'Nombre Apellido'): void
    {
        $sso = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $sso->shouldReceive('getId')->andReturn($id);
        $sso->shouldReceive('getEmail')->andReturn($email);
        $sso->shouldReceive('getName')->andReturn($name);
        $sso->shouldReceive('getNickname')->andReturn(null);
        $sso->user = [];

        Socialite::shouldReceive('driver')->with('azure')->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($sso);
    }

    public function test_redirect_inicia_el_flujo_oauth(): void
    {
        Socialite::shouldReceive('driver')->with('azure')->andReturnSelf();
        Socialite::shouldReceive('redirect')->andReturn(
            new RedirectResponse('https://login.microsoftonline.com/dummy-tenant/oauth2/v2.0/authorize?client_id=dummy-client')
        );

        $this->get(route('sso.redirect'))
            ->assertRedirect();

        $this->assertStringContainsString(
            'login.microsoftonline.com',
            $this->get(route('sso.redirect'))->headers->get('Location'),
        );
    }

    public function test_jit_crea_usuario_con_rol_consulta_y_verificado(): void
    {
        $this->fakeAzureUser('oid-1', 'nuevo@empresa.com', 'Nuevo Usuario');

        $response = $this->get(route('sso.callback'));

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'nuevo@empresa.com')->firstOrFail();
        $this->assertSame('azure', $user->provider);
        $this->assertSame('oid-1', $user->provider_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('consulta'));
    }

    public function test_vincula_usuario_local_existente_por_correo(): void
    {
        $local = User::factory()->create(['email' => 'existente@empresa.com']);
        $local->assignRole('aprobador');

        $this->fakeAzureUser('oid-2', 'existente@empresa.com');

        $this->get(route('sso.callback'))->assertRedirect();

        $local->refresh();
        $this->assertSame('azure', $local->provider);
        $this->assertSame('oid-2', $local->provider_id);
        $this->assertTrue($local->hasRole('aprobador'));
        $this->assertSame(1, User::where('email', 'existente@empresa.com')->count());
    }

    public function test_reingreso_por_sso_no_duplica_usuario(): void
    {
        $this->fakeAzureUser('oid-3', 'recurrente@empresa.com');

        $this->get(route('sso.callback'))->assertRedirect();
        $this->post('/logout');
        $this->get(route('sso.callback'))->assertRedirect();

        $this->assertSame(1, User::where('provider_id', 'oid-3')->count());
    }

    public function test_sin_correo_en_los_claims_rechaza(): void
    {
        $sso = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $sso->shouldReceive('getId')->andReturn('oid-4');
        $sso->shouldReceive('getEmail')->andReturn(null);
        $sso->shouldReceive('getName')->andReturn('Sin Correo');
        $sso->shouldReceive('getNickname')->andReturn(null);
        $sso->user = [];
        Socialite::shouldReceive('driver')->with('azure')->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($sso);

        $this->get(route('sso.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_estado_invalido_vuelve_al_login_con_error(): void
    {
        Socialite::shouldReceive('driver')->with('azure')->andReturnSelf();
        Socialite::shouldReceive('user')->andThrow(new InvalidStateException);

        $this->get(route('sso.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_dominio_no_permitido_rechaza(): void
    {
        config(['sso.allowed_domains' => ['empresa.com']]);
        $this->fakeAzureUser('oid-5', 'ajeno@gmail.com');

        $this->get(route('sso.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::where('email', 'ajeno@gmail.com')->count());
    }

    public function test_sso_deshabilitado_oculta_el_boton(): void
    {
        config(['sso.enabled' => false]);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('Continuar con Microsoft');
    }

    public function test_sso_deshabilitado_devuelve_404(): void
    {
        config(['sso.enabled' => false]);

        $this->get(route('sso.redirect'))->assertNotFound();
        $this->get(route('sso.callback'))->assertNotFound();
    }
}
