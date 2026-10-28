{{--
    1–5 star picker. Real radio buttons underneath (visually hidden, still
    focusable), so arrow keys, screen readers and plain form posts all work;
    Alpine only adds the hover preview and the word ("Very good").
      name      the field name (omit for the "rate all" shortcut)
      shortcut  picking a star here sets every `listen` picker on the page
      listen    follows the shortcut (the customer can still change it after)
    Each star's label is 44×44 for touch.
--}}
@props([
    'name' => null,
    'value' => null,
    'label',
    'required' => false,
    'shortcut' => false,
    'listen' => false,
    'hint' => null,
])

@php
    $uid = 'stars-' . \Illuminate\Support\Str::random(8);
    $words = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Very good', 5 => 'Excellent'];
    $value = (int) $value;
@endphp

<fieldset {{ $attributes->merge(['class' => 'cj-stars' . ($shortcut ? ' cj-stars-shortcut' : '')]) }}
          x-data="{ v: {{ $value }}, hover: 0 }"
          @if($listen) @cj-rate-all.window="v = $event.detail" @endif>
    <legend class="cj-stars-legend">
        {{ $label }}
        @if($required)<span class="cj-stars-req" aria-hidden="true">*</span>@endif
    </legend>
    @if($hint)<p class="cj-stars-hint">{{ $hint }}</p>@endif
    <div class="cj-stars-row" @mouseleave="hover = 0">
        @for($i = 1; $i <= 5; $i++)
            <input type="radio" class="cj-stars-input" id="{{ $uid }}-{{ $i }}"
                   name="{{ $name ?? $uid }}" value="{{ $i }}" x-model.number="v"
                   @if($shortcut) @change="$dispatch('cj-rate-all', {{ $i }})" @endif
                   @checked($value === $i) @if($required && $i === 1) required @endif>
            <label for="{{ $uid }}-{{ $i }}" class="cj-star" :class="{ 'on': (hover || v) >= {{ $i }} }"
                   @mouseenter="hover = {{ $i }}" @class(['on' => $value >= $i])>
                <span aria-hidden="true">★</span>
                <span class="visually-hidden">{{ $i }} {{ $i === 1 ? 'star' : 'stars' }} — {{ $words[$i] }}</span>
            </label>
        @endfor
        <span class="cj-stars-word" aria-hidden="true"
              x-text="{{ \Illuminate\Support\Js::from(array_merge([''], array_values($words))) }}[hover || v] || ''">{{ $words[$value] ?? '' }}</span>
    </div>
</fieldset>
