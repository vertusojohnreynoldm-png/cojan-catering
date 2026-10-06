@props(['value'])

<label {{ $attributes->merge(['class' => 'cj-label']) }}>
    {{ $value ?? $slot }}
</label>
