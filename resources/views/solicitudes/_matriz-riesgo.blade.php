@php
    use App\Enums\Probabilidad;
    use App\Enums\Impacto;
@endphp

<details class="mb-4 rounded-lg border border-ink-200 bg-ink-50 text-sm">
    <summary class="cursor-pointer select-none px-4 py-2 font-medium text-ink-700">
        Tabla de calificación de riesgos (Probabilidad · Impacto · Nivel)
    </summary>
    <div class="space-y-4 px-4 py-3">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <h4 class="mb-1 font-semibold text-ink-800">Probabilidad</h4>
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-left text-ink-500">
                            <th class="py-1 pr-2">Nivel</th><th class="py-1 pr-2">Valor</th><th class="py-1">Criterio orientador</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (Probabilidad::cases() as $p)
                            <tr class="border-t border-ink-200 align-top">
                                <td class="py-1 pr-2 font-medium">{{ $p->etiqueta() }}</td>
                                <td class="py-1 pr-2">{{ $p->value }}</td>
                                <td class="py-1 text-ink-600">{{ $p->criterio() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>
                <h4 class="mb-1 font-semibold text-ink-800">Impacto</h4>
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-left text-ink-500">
                            <th class="py-1 pr-2">Nivel</th><th class="py-1 pr-2">Valor</th><th class="py-1">Criterio orientador</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (Impacto::cases() as $i)
                            <tr class="border-t border-ink-200 align-top">
                                <td class="py-1 pr-2 font-medium">{{ $i->etiqueta() }}</td>
                                <td class="py-1 pr-2">{{ $i->value }}</td>
                                <td class="py-1 text-ink-600">{{ $i->criterio() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-xs text-ink-600">
            <strong>NR = Probabilidad × Impacto.</strong>
            Nivel: <span class="badge badge-green">Bajo</span> NR 1–30 ·
            <span class="badge badge-amber">Medio</span> NR 31–60 ·
            <span class="badge badge-red">Alto</span> NR 61–100.
            Solo los riesgos <strong>Medio</strong> y <strong>Alto</strong> generan una acción en el plan.
        </p>
    </div>
</details>
