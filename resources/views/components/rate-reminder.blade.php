{{--
    Gentle reminder for delivered orders / completed bookings the customer
    hasn't rated yet (only while rating is still open). Dismissing hides it
    in this browser until a new order is waiting (localStorage, optional —
    the banner simply shows again where storage is unavailable).
--}}
@php
    $awaiting = auth()->check() ? \App\Services\Ratings::awaiting(auth()->user()) : collect();
@endphp

@if($awaiting->isNotEmpty())
    @php
        $latest = $awaiting->first();
        $key = 'cj-rate-dismissed:' . $awaiting->pluck('id')->sort()->implode(',');
    @endphp
    <div class="alert-cj alert-info cj-rate-banner" role="status"
         x-data="{ shown: true, key: {{ \Illuminate\Support\Js::from($key) }} }"
         x-init="try { shown = localStorage.getItem(key) !== '1' } catch (e) {}"
         x-show="shown">
        <i class="bi bi-star" aria-hidden="true"></i>
        <span>
            @if($awaiting->count() === 1)
                How was {{ $latest->isBooking() ? 'your catering booking' : 'your order' }} #{{ $latest->order_number }}? We'd love your rating.
            @else
                You have {{ $awaiting->count() }} delivered orders to rate. We'd love to hear how they were.
            @endif
        </span>
        <a href="{{ route('customer.feedback.create', $latest->id) }}" class="btn-cj-amber btn-cj btn-cj-sm">
            Rate {{ $awaiting->count() === 1 ? 'now' : '#' . $latest->order_number }}
        </a>
        <button type="button" class="cj-rate-banner-close" aria-label="Dismiss rating reminder"
                @click="shown = false; try { localStorage.setItem(key, '1') } catch (e) {}">&times;</button>
    </div>
@endif
