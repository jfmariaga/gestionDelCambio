<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proceso;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UsuarioController extends Controller
{
    private const ROLES = ['administrador', 'solicitante', 'dueno_proceso', 'aprobador', 'consulta'];

    public function index()
    {
        $usuarios = User::with('roles', 'procesos')->orderBy('name')->paginate(20);

        return view('admin.usuarios.index', compact('usuarios'));
    }

    public function create()
    {
        return view('admin.usuarios.form', [
            'usuario' => new User,
            'roles' => self::ROLES,
            'procesos' => Proceso::ordenados()->get(),
            'asignados' => [],
            'rolesUsuario' => [],
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::in(self::ROLES)],
            'procesos' => ['array'],
            'procesos.*' => ['integer', 'exists:procesos,id'],
        ]);

        $usuario = User::create([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => Hash::make($datos['password']),
        ]);

        $usuario->syncRoles($datos['roles'] ?? []);
        $usuario->procesos()->sync($this->procesosSiDueno($datos));

        return redirect()->route('admin.usuarios.index')->with('status', "Usuario {$usuario->name} creado.");
    }

    public function edit(User $usuario)
    {
        return view('admin.usuarios.form', [
            'usuario' => $usuario,
            'roles' => self::ROLES,
            'procesos' => Proceso::ordenados()->get(),
            'asignados' => $usuario->procesos->pluck('id')->all(),
            'rolesUsuario' => $usuario->roles->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, User $usuario)
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::in(self::ROLES)],
            'procesos' => ['array'],
            'procesos.*' => ['integer', 'exists:procesos,id'],
        ]);

        $usuario->fill(['name' => $datos['name'], 'email' => $datos['email']]);
        if (! empty($datos['password'])) {
            $usuario->password = Hash::make($datos['password']);
        }
        $usuario->save();

        $usuario->syncRoles($datos['roles'] ?? []);
        $usuario->procesos()->sync($this->procesosSiDueno($datos));

        return redirect()->route('admin.usuarios.index')->with('status', "Usuario {$usuario->name} actualizado.");
    }

    public function destroy(Request $request, User $usuario)
    {
        abort_if($usuario->id === $request->user()->id, 403, 'No puede eliminar su propio usuario.');

        if ($usuario->solicitudesCreadas()->exists()) {
            return back()->with('error', 'No se puede eliminar: el usuario es responsable de solicitudes registradas.');
        }

        $usuario->delete();

        return back()->with('status', 'Usuario eliminado.');
    }

    /** Solo conserva procesos asignados si el usuario es dueño de proceso. */
    private function procesosSiDueno(array $datos): array
    {
        return in_array('dueno_proceso', $datos['roles'] ?? [], true) ? ($datos['procesos'] ?? []) : [];
    }
}
