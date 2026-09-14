<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AreaNotificacion;
use App\Models\DestinatarioArea;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DestinatarioAreaController extends Controller
{
    public function index()
    {
        $areas = AreaNotificacion::with('destinatarios.usuario:id,name,email')->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get(['id', 'name']);

        return view('admin.destinatarios.index', compact('areas', 'usuarios'));
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'area_id' => ['required', Rule::exists('areas_notificacion', 'id')],
            'user_id' => ['nullable', Rule::exists('users', 'id')],
            'email' => ['nullable', 'email', 'max:190'],
        ]);

        if (empty($datos['user_id']) && empty($datos['email'])) {
            return back()->withErrors(['email' => 'Indique un usuario o un correo.']);
        }

        DestinatarioArea::create([
            'area_id' => $datos['area_id'],
            'user_id' => $datos['user_id'] ?: null,
            'email' => $datos['user_id'] ? null : $datos['email'],
            'activo' => true,
        ]);

        return back()->with('status', 'Destinatario agregado.');
    }

    public function update(Request $request, DestinatarioArea $destinatario)
    {
        $destinatario->update(['activo' => $request->boolean('activo')]);

        return back()->with('status', 'Destinatario actualizado.');
    }

    public function destroy(DestinatarioArea $destinatario)
    {
        $destinatario->delete();

        return back()->with('status', 'Destinatario eliminado.');
    }
}
