@props(['value' => null, 'placeholder' => '—'])
@php
    $v = $value;
    $formatted = ($v === null || $v === '' || ! is_numeric($v))
        ? $placeholder
        : \Illuminate\Support\Number::currency(round((float) $v), in: 'COP', locale: 'es_CO');
@endphp
<span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ $formatted }}</span>
