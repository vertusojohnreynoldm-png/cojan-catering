{{-- Read-only stars, e.g. <x-stars :value="4" />. --}}
@props(['value' => 0])
@php $value = max(0, min(5, (int) round($value))); @endphp
<span {{ $attributes->merge(['class' => 'cj-stars-static']) }} role="img" aria-label="{{ $value }} out of 5 stars"><span aria-hidden="true">{{ str_repeat('★', $value) }}<span class="off">{{ str_repeat('★', 5 - $value) }}</span></span></span>
