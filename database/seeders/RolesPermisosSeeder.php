<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermisosSeeder extends Seeder
{
    /**
     * 5 roles y permisos según la "Matriz de permisos por estado de la solicitud" de la spec
     * (FR-034…FR-044). El alcance fino (autor, estado, procesos asignados) lo aplican las Policies.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'solicitudes.crear',
            'solicitudes.ver',
            'solicitudes.editar',        // secciones 1-2, cuestionario, plan (en borrador)
            'solicitudes.secciones34',   // completar consideraciones y riesgos
            'solicitudes.enviar',
            'solicitudes.aprobar',       // aprobar / devolver
            'solicitudes.cerrar',        // cerrar / anular
            'solicitudes.exportar',
            'catalogo.administrar',
            'usuarios.administrar',
        ];

        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso);
        }

        $roles = [
            'administrador' => $permisos,
            'solicitante' => [
                'solicitudes.crear', 'solicitudes.ver', 'solicitudes.editar',
                'solicitudes.secciones34', 'solicitudes.enviar', 'solicitudes.exportar',
            ],
            'dueno_proceso' => [
                'solicitudes.ver', 'solicitudes.secciones34', 'solicitudes.exportar',
            ],
            'aprobador' => [
                'solicitudes.ver', 'solicitudes.aprobar', 'solicitudes.cerrar', 'solicitudes.exportar',
            ],
            'consulta' => [
                'solicitudes.ver', 'solicitudes.exportar',
            ],
        ];

        foreach ($roles as $nombre => $permisosRol) {
            Role::findOrCreate($nombre)->syncPermissions($permisosRol);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
