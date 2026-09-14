<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        $notificaciones = $request->user()->notifications()->paginate(30);

        return view('notificaciones.index', compact('notificaciones'));
    }

    public function leer(Request $request, string $id)
    {
        $request->user()->notifications()->whereKey($id)->update(['read_at' => now()]);

        return back();
    }

    public function leerTodo(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Notificaciones marcadas como leídas.');
    }
}
