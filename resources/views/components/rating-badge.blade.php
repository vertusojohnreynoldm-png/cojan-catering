{{-- "★ 4.6 (23)" from RatingStats (visible reviews only), or "No ratings yet". --}}
@props(['stats' => null])
@if($stats)
    <span {{ $attributes->merge(['class' => 'cj-rating-badge']) }}
          aria-label="Rated {{ number_format($stats['avg'], 1) }} out of 5 from {{ $stats['count'] }} {{ \Illuminate\Support\Str::plural('rating', $stats['count']) }}">
        <span class="cj-rating-badge-star" aria-hidden="true">★</span>
        <span aria-hidden="true"><strong>{{ number_format($stats['avg'], 1) }}</strong> ({{ $stats['count'] }})</span>
    </span>
@else
    <span {{ $attributes->merge(['class' => 'cj-rating-badge cj-rating-badge-none']) }}>No ratings yet</span>
@endif
