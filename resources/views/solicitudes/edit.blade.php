<x-app-layout>
    @php($gating = app(\App\Domain\GestionCambio\GatingSecciones::class))
    @php($clasificacion = $solicitud->clasificacionVigente())

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('solicitudes.show', $solicitud) }}" class="btn btn-ghost -ml-2 p-2">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs text-ink-400">{{ $solicitud->consecutivo }}</span>
                        <span class="badge {{ $solicitud->estado->badgeClass() }}">{{ $solicitud->estado->etiqueta() }}</span>
                        @if ($clasificacion)
                            <span class="badge badge-brand">{{ $clasificacion->value }}</span>
                        @endif
                    </div>
                    <h1 class="text-lg font-semibold text-ink-900">{{ $solicitud->nombre_cambio }}</h1>
                </div>
            </div>
            @if ($solicitud->estado === \App\Enums\EstadoSolicitud::Solicitado)
                <form method="POST" action="{{ route('solicitudes.enviar', $solicitud) }}"
                      data-confirm="La solicitud quedará de solo lectura mientras está en aprobación."
                      data-confirm-title="Enviar a aprobación" data-confirm-icon="warning">
                    @csrf
                    <button class="btn btn-success">Enviar a aprobación</button>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="lg:grid lg:grid-cols-[13rem_minmax(0,1fr)] lg:gap-8">
        {{-- Índice de secciones --}}
        <nav class="mb-4 hidden lg:block">
            <div class="sticky top-24 space-y-1 text-sm">
                @foreach ([
                    'sec-eval' => '1. Evaluación',
                    'sec-1' => '2. Resumen y descripción',
                    'sec-3q' => '3. Cuestionario',
                    'sec-4' => '4. Riesgos asociados',
                    'sec-5' => '5. Plan de acción',
                    'sec-6' => '6. Seguimiento y cierre',
                ] as $anchor => $label)
                    <a href="#{{ $anchor }}" class="block rounded-lg px-3 py-1.5 text-ink-500 hover:bg-ink-100 hover:text-ink-900">{{ $label }}</a>
                @endforeach
            </div>
        </nav>

        <div class="space-y-5">
            {{-- 1. Evaluación y clasificación (va PRIMERO: define el resto del formulario) --}}
            <section id="sec-eval" class="card card-pad scroll-mt-24">
                <div class="mb-4 flex items-center gap-3">
                    <span class="section-num">1</span>
                    <div>
                        <h2 class="text-sm font-semibold text-ink-900">Evaluación y clasificación del cambio</h2>
                        <p class="text-xs text-ink-400">Califique los 11 criterios. La clasificación resultante habilita las demás secciones y asigna el aprobador.</p>
                    </div>
                    @if ($clasificacion)
                        <span class="badge badge-brand ml-auto">{{ $clasificacion->value }}</span>
                    @else
                        <span class="badge badge-gray ml-auto">Sin clasificar</span>
                    @endif
                </div>
                <livewire:solicitud.evaluacion :solicitud="$solicitud" />
            </section>

            {{-- 2. Resumen y descripción --}}
            <section id="sec-1" class="card card-pad scroll-mt-24">
                <div class="mb-5 flex items-center gap-3">
                    <span class="section-num">2</span>
                    <h2 class="text-sm font-semibold text-ink-900">
                        Resumen y descripción <span class="font-normal text-ink-400">· FOSIG-02 secciones 1 y 2</span>
                    </h2>
                    @if ($clasificacion)
                        <span class="badge badge-brand ml-auto">Clasificación: {{ $clasificacion->value }}</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('solicitudes.update', $solicitud) }}">
                    @csrf @method('PUT')
                    @include('solicitudes._form', ['solicitud' => $solicitud])
                    <div class="mt-5 flex justify-end">
                        <button class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </section>

            @php($bloqueado = '<div class="rounded-lg border border-dashed border-ink-200 bg-ink-50 px-4 py-6 text-center text-sm text-ink-400">Complete la evaluación para habilitar esta sección.</div>')
            @php($bloqueadoPorCuestionario = '<div class="rounded-lg border border-dashed border-ink-200 bg-ink-50 px-4 py-6 text-center text-sm text-ink-400">Complete el cuestionario (sección 3) al 100% para habilitar esta sección.</div>')
            @php($cuestionarioPendiente = $gating->cuestionarioPendiente($solicitud))

            {{-- 3. Cuestionario --}}
            <section id="sec-3q" class="card card-pad scroll-mt-24">
                <div class="mb-4 flex items-center gap-3">
                    <span class="section-num">3</span>
                    <div>
                        <h2 class="text-sm font-semibold text-ink-900">Cuestionario de riesgos <span class="font-normal text-ink-400">· FOSIG-02 sección 3</span></h2>
                        <p class="text-xs text-ink-400">Marque "Sí" donde el cambio pueda afectar el proceso; los riesgos se precargan abajo.</p>
                    </div>
                </div>
                @if ($gating->permite($solicitud, 'cuestionario'))
                    <livewire:solicitud.cuestionario :solicitud="$solicitud" />
                @elseif ($clasificacion === \App\Enums\Clasificacion::Menor)
                    <div class="rounded-lg border border-dashed border-ink-200 bg-ink-50 px-4 py-6 text-center text-sm text-ink-400">
                        El cambio es <strong>Menor</strong>: no requiere cuestionario ni evaluación de riesgos. Continúe con el Plan de acción.
                    </div>
                @else
                    {!! $bloqueado !!}
                @endif
            </section>

            {{-- 4. Riesgos asociados --}}
            <section id="sec-4" class="card card-pad scroll-mt-24">
                <div class="mb-4 flex items-center gap-3">
                    <span class="section-num">4</span>
                    <h2 class="text-sm font-semibold text-ink-900">Riesgos asociados <span class="font-normal text-ink-400">· FOSIG-02 sección 4</span></h2>
                </div>
                @include('solicitudes._matriz-riesgo')
                @if ($gating->permite($solicitud, 'riesgos'))
                    <livewire:solicitud.riesgos-asociados :solicitud="$solicitud" />
                @elseif ($clasificacion === \App\Enums\Clasificacion::Menor)
                    <div class="rounded-lg border border-dashed border-ink-200 bg-ink-50 px-4 py-6 text-center text-sm text-ink-400">No aplica para cambios Menores.</div>
                @elseif ($cuestionarioPendiente)
                    {!! $bloqueadoPorCuestionario !!}
                @else
                    {!! $bloqueado !!}
                @endif
            </section>

            {{-- 5. Plan de acción --}}
            <section id="sec-5" class="card card-pad scroll-mt-24">
                <div class="mb-4 flex items-center gap-3">
                    <span class="section-num">5</span>
                    <h2 class="text-sm font-semibold text-ink-900">Plan de acción y seguimiento <span class="font-normal text-ink-400">· FOSIG-02 sección 5</span></h2>
                </div>
                @if ($gating->permite($solicitud, 'plan'))
                    <livewire:solicitud.plan-accion :solicitud="$solicitud" />
                @elseif ($cuestionarioPendiente)
                    {!! $bloqueadoPorCuestionario !!}
                @else
                    {!! $bloqueado !!}
                @endif
            </section>

            {{-- 6. Seguimiento y cierre --}}
            <section id="sec-6" class="card card-pad scroll-mt-24">
                <div class="mb-4 flex items-center gap-3">
                    <span class="section-num">6</span>
                    <h2 class="text-sm font-semibold text-ink-900">Seguimiento y cierre <span class="font-normal text-ink-400">· FOSIG-02 sección 6</span></h2>
                </div>
                @if ($gating->permite($solicitud, 'cierre'))
                    <livewire:solicitud.aprobacion-cierre :solicitud="$solicitud" />
                @elseif ($cuestionarioPendiente)
                    {!! $bloqueadoPorCuestionario !!}
                @else
                    {!! $bloqueado !!}
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
