<?php

namespace Tests\Feature;

use App\Models\Proceso;
use App\Models\SolicitudCambio;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuarioAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('administrador');

        return $u;
    }

    public function test_no_admin_no_entra_a_la_administracion_de_usuarios(): void
    {
        $u = User::factory()->create();
        $u->assignRole('aprobador');

        $this->actingAs($u)->get(route('admin.usuarios.index'))->assertForbidden();
    }

    public function test_admin_crea_usuario_con_roles_y_procesos(): void
    {
        $proceso = Proceso::factory()->create();

        $this->actingAs($this->admin())->post(route('admin.usuarios.store'), [
            'name' => 'Jefe de Producción',
            'email' => 'jefe.produccion@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['dueno_proceso'],
            'procesos' => [$proceso->id],
        ])->assertRedirect(route('admin.usuarios.index'));

        $user = User::where('email', 'jefe.produccion@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('dueno_proceso'));
        $this->assertEqualsCanonicalizing([$proceso->id], $user->procesos->pluck('id')->all());
    }

    public function test_procesos_se_ignoran_si_no_es_dueno_de_proceso(): void
    {
        $proceso = Proceso::factory()->create();

        $this->actingAs($this->admin())->post(route('admin.usuarios.store'), [
            'name' => 'Aprobador X',
            'email' => 'aprobador.x@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['aprobador'],
            'procesos' => [$proceso->id],
        ])->assertRedirect();

        $user = User::where('email', 'aprobador.x@example.com')->firstOrFail();
        $this->assertCount(0, $user->procesos);
    }

    public function test_admin_actualiza_roles_sin_cambiar_contrasena(): void
    {
        $user = User::factory()->create();
        $user->assignRole('solicitante');
        $hash = $user->password;

        $this->actingAs($this->admin())->put(route('admin.usuarios.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => ['solicitante', 'aprobador'],
        ])->assertRedirect();

        $user->refresh();
        $this->assertTrue($user->hasRole('aprobador'));
        $this->assertSame($hash, $user->password);
    }

    public function test_no_se_elimina_usuario_responsable_de_solicitudes(): void
    {
        $user = User::factory()->create();
        SolicitudCambio::factory()->create(['created_by' => $user->id]);

        $this->actingAs($this->admin())->delete(route('admin.usuarios.destroy', $user))->assertRedirect();

        $this->assertModelExists($user);
    }
}
