<?php

namespace App\Http\Controllers;

use App\Domain\GestionCambio\MetricasDashboard;
use App\Enums\Clasificacion;
use App\Enums\EstadoSolicitud;
use App\Enums\TipoCambio;
use App\Models\Proceso;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request, MetricasDashboard $servicio)
    {
        if (! Auth::user()->hasRole('administrador')) {
            return view('dashboard');
        }

        $filtros = $request->only([
            'responsable_id', 'lider_id', 'proceso', 'tipo_cambio', 'clasificacion', 'estado', 'fecha_desde', 'fecha_hasta',
        ]);

        $metricas = $servicio->calcular($filtros);
        $tablaResponsables = $servicio->tablaPorResponsable($servicio->accionesFiltradas($filtros));
        $evolucionMensual = $servicio->evolucionMensual($servicio->accionesFiltradas($filtros));

        $filtrosDatos = [
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
            'procesos' => Proceso::activos()->ordenados()->pluck('nombre'),
            'tipos_cambio' => TipoCambio::opciones(),
            'clasificaciones' => Clasificacion::cases(),
            'estados' => EstadoSolicitud::cases(),
        ];

        return view('dashboard.admin', [
            'metricas' => $metricas,
            'tablaResponsables' => $tablaResponsables,
            'evolucionMensual' => $evolucionMensual,
            'filtros' => $filtros,
        ] + $filtrosDatos);
    }
}
