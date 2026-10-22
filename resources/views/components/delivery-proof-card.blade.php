{{--
    Shared by customer/order-detail.blade.php and customer/order-track.blade.php
    so the "Delivered" proof display and the confirm-receipt button aren't
    duplicated across both. Not shown at all for bookings (no rider/delivery
    involved) or before a delivery/proof actually exists.
--}}
@props(['order'])

@if(!$order->isBooking() && $order->delivery && $order->delivery->status === 'delivered')
<div class="cj-card mb-3">
    <div class="cj-card-header">📦 Delivered</div>
    <div class="cj-card-body">
        <div class="row g-3">
            @if($order->delivery->proof_photo_path)
                <div class="col-12 col-sm-5">
                    <img src="{{ route('orders.proof-photo', $order->id) }}" alt="Proof of delivery photo"
                         style="width:100%;border-radius:var(--radius-base);box-shadow:var(--shadow-sm);display:block;">
                </div>
            @endif
            <div class="col-12 {{ $order->delivery->proof_photo_path ? 'col-sm-7' : '' }}">
                <p style="margin-bottom:.4rem;">
                    <strong>Delivered:</strong> {{ $order->delivery->delivered_at?->format('M d, Y h:i A') }}
                </p>
                @if($order->delivery->received_by)
                    <p style="margin-bottom:.4rem;"><strong>Received by:</strong> {{ $order->delivery->received_by }}</p>
                @endif
                @if($order->delivery->delivery_note)
                    <p style="margin-bottom:.75rem;"><strong>Note:</strong> {{ $order->delivery->delivery_note }}</p>
                @endif

                @if($order->delivery->customer_confirmed_at)
                    <p style="color:var(--green-dark);font-weight:600;margin-bottom:0;">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        You confirmed receipt on {{ $order->delivery->customer_confirmed_at->format('M d, Y h:i A') }}
                    </p>
                @else
                    <form method="POST" action="{{ route('customer.orders.confirm-received', $order->id) }}">
                        @csrf
                        <button type="submit" class="btn-cj-amber btn-cj">
                            <i class="bi bi-check2-circle" aria-hidden="true"></i> Confirm I Received My Order
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endif
