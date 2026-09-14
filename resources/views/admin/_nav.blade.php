@php($tabs = [
    'admin.usuarios.index' => 'Usuarios y roles',
    'admin.catalogo.procesos.index' => 'Procesos',
    'admin.catalogo.riesgos.index' => 'Riesgos predeterminados',
    'admin.catalogo.preguntas.index' => 'Preguntas clave',
    'admin.plantas.index' => 'Plantas',
    'admin.destinatarios.index' => 'Destinatarios',
])
<nav class="mb-5 flex flex-wrap gap-1 rounded-lg border border-ink-200 bg-white p-1 text-sm">
    @foreach ($tabs as $route => $label)
        <a href="{{ route($route) }}"
           @class([
               'rounded-md px-3 py-1.5 font-medium transition-colors',
               'bg-brand-700 text-white' => request()->routeIs($route),
               'text-ink-600 hover:bg-ink-100' => ! request()->routeIs($route),
           ])>{{ $label }}</a>
    @endforeach
</nav>
