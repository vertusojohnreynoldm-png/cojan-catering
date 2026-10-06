{{-- Usage: <x-order-timeline :order="$order" />
     Distinguishes done / current / pending steps (the previous inline version
     only had a 2-way done/pending split, so the current step looked identical
     to already-completed ones). Cancelled orders get their own state instead
     of running through array_search() against a list that doesn't include
     "cancelled" — the old inline version did that, and PHP's loose comparison
     against the resulting `false` silently produced a nonsensical partial-
     progress render instead of failing loudly. --}}
@props(['order'])

@if($order->status === 'cancelled')
    <div class="alert-cj alert-danger" style="display:block;">
        <strong>❌ This order was cancelled.</strong>
    </div>
@else
    @php
        $statuses = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered'];
        $currentIndex = array_search($order->status, $statuses, true);
    @endphp
    <div class="cj-timeline">
        @foreach($statuses as $index => $status)
            @php
                $state = $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'current' : 'pending');
            @endphp
            <div class="track-step">
                <div class="track-circle {{ $state }}">
                    {{ $state === 'done' ? '✓' : $index + 1 }}
                </div>
                <div class="track-label {{ $state !== 'pending' ? 'done' : '' }}">
                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                </div>
            </div>
            @if(!$loop->last)
                <div class="track-line {{ $index < $currentIndex ? 'done' : '' }}"></div>
            @endif
        @endforeach
    </div>
@endif
