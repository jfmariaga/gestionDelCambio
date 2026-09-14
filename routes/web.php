<?php

use App\Http\Controllers\AdjuntoController;
use App\Http\Controllers\Admin\DestinatarioAreaController;
use App\Http\Controllers\Admin\PlantaController;
use App\Http\Controllers\Admin\PreguntaClaveController;
use App\Http\Controllers\Admin\ProcesoController;
use App\Http\Controllers\Admin\RiesgoPredeterminadoController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PlantaSesionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SolicitudCambioController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Selección de planta / sede activa (Revisión R2 / US12) — sin el middleware 'planta'.
    Route::get('/plantas/seleccionar', [PlantaSesionController::class, 'show'])->name('plantas.seleccionar');
    Route::post('/plantas/seleccionar', [PlantaSesionController::class, 'store'])->name('plantas.seleccionar.store');
    Route::patch('/plantas/activa', [PlantaSesionController::class, 'cambiar'])->name('plantas.activa');

    // Bandeja de notificaciones in-app (Revisión R2 / US11)
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::post('/notificaciones/{id}/leer', [NotificacionController::class, 'leer'])->name('notificaciones.leer');
    Route::post('/notificaciones/leer-todo', [NotificacionController::class, 'leerTodo'])->name('notificaciones.leerTodo');

    Route::middleware('planta')->group(function () {
        Route::get('/solicitudes', [SolicitudCambioController::class, 'index'])->name('solicitudes.index');
        Route::get('/solicitudes/crear', [SolicitudCambioController::class, 'create'])->name('solicitudes.create');
        Route::post('/solicitudes', [SolicitudCambioController::class, 'store'])->name('solicitudes.store');
        Route::get('/solicitudes/{solicitud}', [SolicitudCambioController::class, 'show'])->name('solicitudes.show');
        Route::get('/solicitudes/{solicitud}/editar', [SolicitudCambioController::class, 'edit'])->name('solicitudes.edit');
        Route::put('/solicitudes/{solicitud}', [SolicitudCambioController::class, 'update'])->name('solicitudes.update');
        Route::post('/solicitudes/{solicitud}/enviar', [SolicitudCambioController::class, 'enviar'])->name('solicitudes.enviar');
        Route::post('/solicitudes/{solicitud}/decision', [SolicitudCambioController::class, 'decision'])->name('solicitudes.decision');
        Route::post('/solicitudes/{solicitud}/implementar', [SolicitudCambioController::class, 'implementar'])->name('solicitudes.implementar');
        Route::post('/solicitudes/{solicitud}/enviar-verificacion', [SolicitudCambioController::class, 'enviarVerificacion'])->name('solicitudes.enviarVerificacion');
        Route::post('/solicitudes/{solicitud}/cerrar', [SolicitudCambioController::class, 'cerrar'])->name('solicitudes.cerrar');
        Route::post('/solicitudes/{solicitud}/anular', [SolicitudCambioController::class, 'anular'])->name('solicitudes.anular');
        Route::patch('/solicitudes/{solicitud}/aprobador', [SolicitudCambioController::class, 'aprobador'])->name('solicitudes.aprobador');
        Route::get('/solicitudes/{solicitud}/exportar', [SolicitudCambioController::class, 'exportar'])->name('solicitudes.exportar');
        Route::get('/solicitudes/{solicitud}/adjuntos/{adjunto}', [AdjuntoController::class, 'download'])->name('solicitudes.adjuntos.download');
    });

    /*
    |--------------------------------------------------------------------------
    | Administración del portal (solo rol administrador)
    |--------------------------------------------------------------------------
    */
    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => redirect()->route('admin.usuarios.index'))->name('index');

        // Usuarios y roles
        Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::get('usuarios/crear', [UsuarioController::class, 'create'])->name('usuarios.create');
        Route::post('usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::get('usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
        Route::put('usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::delete('usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');

        // Catálogo: procesos
        Route::get('catalogo/procesos', [ProcesoController::class, 'index'])->name('catalogo.procesos.index');
        Route::post('catalogo/procesos', [ProcesoController::class, 'store'])->name('catalogo.procesos.store');
        Route::put('catalogo/procesos/{proceso}', [ProcesoController::class, 'update'])->name('catalogo.procesos.update');
        Route::delete('catalogo/procesos/{proceso}', [ProcesoController::class, 'destroy'])->name('catalogo.procesos.destroy');

        // Catálogo: riesgos predeterminados
        Route::get('catalogo/riesgos', [RiesgoPredeterminadoController::class, 'index'])->name('catalogo.riesgos.index');
        Route::post('catalogo/riesgos', [RiesgoPredeterminadoController::class, 'store'])->name('catalogo.riesgos.store');
        Route::put('catalogo/riesgos/{riesgo}', [RiesgoPredeterminadoController::class, 'update'])->name('catalogo.riesgos.update');
        Route::delete('catalogo/riesgos/{riesgo}', [RiesgoPredeterminadoController::class, 'destroy'])->name('catalogo.riesgos.destroy');

        // Catálogo: preguntas clave
        Route::get('catalogo/preguntas', [PreguntaClaveController::class, 'index'])->name('catalogo.preguntas.index');
        Route::post('catalogo/preguntas', [PreguntaClaveController::class, 'store'])->name('catalogo.preguntas.store');
        Route::put('catalogo/preguntas/{pregunta}', [PreguntaClaveController::class, 'update'])->name('catalogo.preguntas.update');
        Route::delete('catalogo/preguntas/{pregunta}', [PreguntaClaveController::class, 'destroy'])->name('catalogo.preguntas.destroy');

        // Importación masiva desde el archivo base
        Route::post('catalogo/importar', [CatalogoController::class, 'importar'])->name('catalogo.importar');

        // Plantas / sedes (Revisión R2 / US12)
        Route::get('plantas', [PlantaController::class, 'index'])->name('plantas.index');
        Route::post('plantas', [PlantaController::class, 'store'])->name('plantas.store');
        Route::put('plantas/{planta}', [PlantaController::class, 'update'])->name('plantas.update');
        Route::delete('plantas/{planta}', [PlantaController::class, 'destroy'])->name('plantas.destroy');

        // Destinatarios de notificación por área (Revisión R2 / US11)
        Route::get('notificaciones/destinatarios', [DestinatarioAreaController::class, 'index'])->name('destinatarios.index');
        Route::post('notificaciones/destinatarios', [DestinatarioAreaController::class, 'store'])->name('destinatarios.store');
        Route::put('notificaciones/destinatarios/{destinatario}', [DestinatarioAreaController::class, 'update'])->name('destinatarios.update');
        Route::delete('notificaciones/destinatarios/{destinatario}', [DestinatarioAreaController::class, 'destroy'])->name('destinatarios.destroy');
    });
});

require __DIR__.'/auth.php';
