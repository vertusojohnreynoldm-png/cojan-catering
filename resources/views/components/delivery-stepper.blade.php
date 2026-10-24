{{--
    Menu-order delivery stepper (customer tracking page):
      Confirmed → Preparing → Out for Delivery → Delivered
    "Waiting for confirmation" is shown above it while the order is still
    pending; a cancelled order gets its own final state instead.
    Catering bookings keep <x-order-timeline> — this is menu orders only.

    Props: order (Order)
--}}
@props(['order'])

@php
    $steps = [
        'confirmed'        => ['Confirmed', 'bi-check2-circle'],
        'preparing'        => ['Preparing', 'bi-fire'],
        'out_for_delivery' => ['Out for Delivery', 'bi-bicycle'],
        'delivered'        => ['Delivered', 'bi-house-check'],
    ];
    $keys = array_keys($steps);
    $current = array_search($order->status, $keys, true); // false while pending
@endphp

@if($order->status === 'cancelled')
    <div class="cj-final cj-final-cancelled" role="status">
        <i class="bi bi-x-circle" aria-hidden="true"></i>
        <div>
            <strong>This order was cancelled.</strong>
            <span>If you have questions, message us using the chat.</span>
        </div>
    </div>
@else
    @if($order->status === 'pending')
        <div class="cj-progress-waiting" role="status">
            <span class="cj-progress-waiting-dot" aria-hidden="true"></span>
            Waiting for confirmation — we'll start preparing your order as soon as it's confirmed.
        </div>
    @endif

    <ol class="cj-progress" aria-label="Order progress">
        @foreach($steps as $key => [$label, $icon])
            @php
                $i = $loop->index;
                $state = $current === false ? 'todo' : ($i < $current ? 'done' : ($i === $current ? 'now' : 'todo'));
                if ($order->status === 'delivered' && $key === 'delivered') {
                    $state = 'done';
                }
            @endphp
            <li class="cj-pstep {{ $state }}" @if($state === 'now') aria-current="step" @endif>
                <span class="cj-pstep-dot" aria-hidden="true">
                    <i class="bi {{ $state === 'done' ? 'bi-check-lg' : $icon }}"></i>
                </span>
                <span class="cj-pstep-label">{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    @if($order->status === 'delivered')
        <div class="cj-final cj-final-delivered" role="status">
            <i class="bi bi-house-check" aria-hidden="true"></i>
            <div>
                <strong>Delivered{{ $order->delivery?->delivered_at ? ' at ' . $order->delivery->delivered_at->format('g:i A, M d') : '' }}.</strong>
                <span>Enjoy your meal! Live tracking has ended.</span>
            </div>
        </div>
    @endif
@endif
