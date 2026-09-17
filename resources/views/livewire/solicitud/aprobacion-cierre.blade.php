<div class="space-y-3">
    <div>
        <label class="field-label">Nota de cierre</label>
        <textarea class="textarea w-full" rows="5" wire:model="notaCierre" @disabled(!$esLider)
                  placeholder="Resuma cómo se implementó el cambio y por qué se considera listo para cerrar."></textarea>
    </div>

    @if ($esLider)
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn btn-secondary btn-sm" wire:click="guardar">Guardar</button>

            @if ($puedeEnviar)
                <button type="button" class="btn btn-success btn-sm"
                        x-on:click="window.confirmar({title:'Enviar a cierre', text:'Se notificará a los responsables implicados para que aprueben el cierre.'}).then(ok => ok && $wire.enviarACierre())">
                    Enviar a cierre
                </button>
            @elseif ($solicitud->estado === \App\Enums\EstadoSolicitud::EnVerificacion)
                <span class="text-xs text-ink-400">Enviado a verificación — esperando la decisión de los responsables implicados.</span>
            @endif

            <button type="button" class="btn btn-warning btn-sm" wire:click="comprobarCierre">Comprobar requisitos de cierre</button>
        </div>
    @endif

    @if ($bloqueos)
        <ul class="list-disc rounded-lg border border-rose-200 bg-rose-50 p-3 pl-8 text-sm text-rose-700">
            @foreach ($bloqueos as $b)<li>{{ $b }}</li>@endforeach
        </ul>
    @elseif ($comprobado)
        <p class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
            Sin bloqueos: la solicitud está lista para cerrarse.
        </p>
    @endif
</div>
