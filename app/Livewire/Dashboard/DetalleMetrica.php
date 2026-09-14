<?php

namespace App\Livewire\Dashboard;

use App\Domain\GestionCambio\MetricasDashboard;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DetalleMetrica extends Component
{
    /** @var array<string,int> */
    public array $metricas = [];

    /** @var array<string,mixed> */
    public array $filtros = [];

    public ?string $metricaActual = null;

    /**
     * @param  array<string,int>  $metricas
     * @param  array<string,mixed>  $filtros
     */
    public function mount(array $metricas, array $filtros): void
    {
        $this->metricas = $metricas;
        $this->filtros = $filtros;
    }

    public function abrir(string $metrica): void
    {
        if (! array_key_exists($metrica, MetricasDashboard::ETIQUETAS)) {
            return;
        }

        $this->metricaActual = $metrica;
    }

    public function cerrar(): void
    {
        $this->metricaActual = null;
    }

    #[Computed]
    public function items(): Collection
    {
        if ($this->metricaActual === null) {
            return collect();
        }

        return app(MetricasDashboard::class)->itemsPara($this->metricaActual, $this->filtros);
    }

    #[Computed]
    public function esDeSolicitudes(): bool
    {
        return $this->metricaActual !== null
            && app(MetricasDashboard::class)->esMetricaDeSolicitudes($this->metricaActual);
    }

    public function etiqueta(string $metrica): string
    {
        return MetricasDashboard::ETIQUETAS[$metrica] ?? $metrica;
    }

    public function render()
    {
        return view('livewire.dashboard.detalle-metrica');
    }
}
