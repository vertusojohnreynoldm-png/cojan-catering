{{--
    Order QR card (MENU orders only — callers skip it for bookings).
    The QR is printed ONLY while the rider has the order (Order::qrStage()
    === 'active'). Before that a friendly note shows; once delivered,
    cancelled or failed, the final state shows instead. In both cases the
    QR markup never reaches the page.

    Props:
      order (Order)
      poll  (bool) — order detail page only: ask track.live every 30 s and
                     reload when the QR window opens or closes. The tracking
                     page already polls the same endpoint itself.
--}}
@props(['order', 'poll' => false])

@php
    $stage = $order->qrStage();
@endphp

<div {{ $attributes->merge(['class' => 'cj-card h-100 cj-qr-card']) }} data-qr-stage="{{ $stage }}">
    <div class="cj-card-header">QR Code</div>
    <div class="cj-card-body">
        @if($stage === 'active' && $order->qr_code)
            <div class="text-center py-2">
                <div role="img" aria-label="QR code to track order {{ $order->order_number }}" class="cj-order-qr">
                    <div aria-hidden="true">{!! $order->qr_code !!}</div>
                </div>
                <p class="cj-qr-caption">Your rider has your order. Scan this QR code to track it.</p>
            </div>
        @elseif($stage === 'active')
            <p class="cj-qr-caption mb-0">QR code not available.</p>
        @elseif($stage === 'waiting')
            <div class="cj-qr-waiting" role="status">
                <i class="bi bi-qr-code" aria-hidden="true"></i>
                <span>Your QR code will appear once the rider picks up your order.</span>
            </div>
        @elseif($stage === 'delivered')
            <div class="cj-final cj-final-delivered" role="status">
                <i class="bi bi-house-check" aria-hidden="true"></i>
                <div><strong>Delivered</strong><span>This order is complete — no QR code is needed anymore.</span></div>
            </div>
        @elseif($stage === 'failed')
            <div class="cj-final cj-final-cancelled" role="status">
                <i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
                <div><strong>Delivery not completed</strong><span>The rider couldn't complete this delivery. Message us using the chat and we'll sort it out.</span></div>
            </div>
        @else
            <div class="cj-final cj-final-cancelled" role="status">
                <i class="bi bi-x-circle" aria-hidden="true"></i>
                <div><strong>Cancelled</strong><span>This order was cancelled — there's no QR code for it.</span></div>
            </div>
        @endif
    </div>
</div>

@if($poll && in_array($stage, ['waiting', 'active'], true))
<script>
/* Reload once the QR window opens (rider picked up) or closes (delivered,
   cancelled, failed). Reuses the tracking page's live endpoint. */
(function () {
    const LIVE_URL = {{ \Illuminate\Support\Js::from(route('customer.orders.track.live', $order->order_number)) }};
    const wasActive = {{ $stage === 'active' ? 'true' : 'false' }};
    const timer = setInterval(async () => {
        try {
            const resp = await fetch(LIVE_URL, { headers: { 'Accept': 'application/json' } });
            if (!resp.ok) return; // 429 / offline: try again next time
            const s = await resp.json();
            if (s.final || s.active !== wasActive) { clearInterval(timer); window.location.reload(); }
        } catch (e) { /* offline: the next poll retries */ }
    }, 30000);
})();
</script>
@endif
