{{-- Usage: <x-breadcrumbs :items="['Menu' => route('customer.menu'), 'Chicken Inasal' => null]" />
     Array key = label, value = route URL (or null for the current/unlinked page).
     The last item is always rendered unlinked regardless of whether a route was passed. --}}
@props(['items' => []])
<nav aria-label="breadcrumb" class="cj-breadcrumb mb-2">
    <a href="{{ route('customer.dashboard') }}" class="cj-breadcrumb-link">Home</a>
    @foreach($items as $label => $url)
        <span class="cj-breadcrumb-sep">/</span>
        @if($loop->last || !$url)
            <span class="cj-breadcrumb-current">{{ $label }}</span>
        @else
            <a href="{{ $url }}" class="cj-breadcrumb-link">{{ $label }}</a>
        @endif
    @endforeach
</nav>
