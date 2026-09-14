@props([
    'users',
    'model',
    'current' => null,
    'placeholder' => 'Sin asignar',
])

<select {{ $attributes->merge(['class' => 'select input-sm']) }} wire:model="{{ $model }}">
    <option value="">{{ $placeholder }}</option>
    @foreach ($users as $u)
        <option value="{{ $u->id }}" @selected((string) $current === (string) $u->id)>{{ $u->name }}</option>
    @endforeach
</select>
