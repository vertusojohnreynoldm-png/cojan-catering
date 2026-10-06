@extends('layouts.customer')

@section('title', 'Order Detail — Cojan Catering')

@section('content')
<x-breadcrumbs :items="['My Orders' => route('customer.orders'), 'Order #' . $order->order_number => null]" />
<h1 class="cj-page-title">Order Details</h1>
<p class="cj-page-sub">Order #{{ $order->order_number }}</p>

<!-- Order Progress -->
<div class="cj-card mb-3">
    <div class="cj-card-header">Order Progress</div>
    <div class="cj-card-body">
        <x-order-timeline :order="$order" />
    </div>
</div>

<div class="row g-3">
    <!-- Order Info -->
    <div class="col-12 {{ $order->isBooking() ? '' : 'col-md-6' }}">
        <div class="cj-card h-100">
            <div class="cj-card-header">Order Information</div>
            <div class="cj-card-body">
                <table style="width:100%;font-size:.9rem;border-collapse:collapse;">
                    <tr>
                        <td style="padding:6px 0;color:var(--text-light);width:40%;">Order #</td>
                        <td style="padding:6px 0;font-weight:600;">{{ $order->order_number }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-light);">Status</td>
                        <td style="padding:6px 0;">
                            <x-order-status-badge :order="$order" />
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-light);">Payment</td>
                        <td style="padding:6px 0;">
                            <span class="badge-cj {{ $order->payment_status == 'paid' ? 'badge-delivered' : 'badge-pending' }}">
                                {{ ucfirst($order->payment_status) }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-light);">Address</td>
                        <td style="padding:6px 0;">{{ $order->delivery_address }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-light);">Contact</td>
                        <td style="padding:6px 0;">{{ $order->delivery_phone }}</td>
                    </tr>
                    @if($order->notes)
                    <tr>
                        <td style="padding:6px 0;color:var(--text-light);">Notes</td>
                        <td style="padding:6px 0;">{{ $order->notes }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:6px 0;color:var(--text-light);">Date</td>
                        <td style="padding:6px 0;">{{ $order->created_at->format('M d, Y h:i A') }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- QR Code — bookings have no rider to scan/track against, so this is
         hidden entirely for them. QR generation itself is untouched; it's
         still generated at order creation, just not shown here. -->
    @unless($order->isBooking())
    <div class="col-12 col-md-6">
        <div class="cj-card h-100">
            <div class="cj-card-header">QR Code</div>
            <div class="cj-card-body text-center py-4">
                @if($order->qr_code)
                    <div role="img" aria-label="QR code to track order {{ $order->order_number }}"
                         style="display:inline-block;padding:16px;background:#fff;
                                border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.08);">
                        <div aria-hidden="true">{!! $order->qr_code !!}</div>
                    </div>
                    <p style="color:var(--text-light);font-size:.8rem;margin-top:1rem;">
                        Scan this QR code to track your order
                    </p>
                @else
                    <p style="color:var(--text-light);">QR Code not available</p>
                @endif
            </div>
        </div>
    </div>
    @endunless

    <!-- Order Items -->
    <div class="col-12">
        <div class="cj-card">
            <div class="cj-card-header">Order Items</div>
            <div class="cj-card-body p-0">
                <div class="table-responsive">
                    <table class="cj-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Price</th>
                                <th>Qty</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->orderItems as $item)
                            <tr>
                                <td>{{ $item->displayName() }}</td>
                                <td>₱{{ number_format($item->unit_price, 2) }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>₱{{ number_format($item->subtotal, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Subtotal</strong></td>
                                <td><strong>₱{{ number_format($order->subtotal, 2) }}</strong></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Delivery Fee</strong></td>
                                <td><strong>₱{{ number_format($order->delivery_fee, 2) }}</strong></td>
                            </tr>
                            <tr style="background:#f4f9f6;">
                                <td colspan="3" class="text-end"><strong>Total</strong></td>
                                <td>
                                    <strong style="color:var(--green-dark);font-size:1.1rem;">
                                        ₱{{ number_format($order->total_amount, 2) }}
                                    </strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-3 flex-wrap">
    <a href="{{ route('customer.orders') }}"
       style="color:var(--green-dark);border:1.5px solid var(--green-dark);
              border-radius:10px;padding:8px 18px;text-decoration:none;font-size:.9rem;">
        ← Back to Orders
    </a>
    @if($order->status === 'delivered')
        <a href="{{ route('customer.feedback.create', $order->id) }}" class="btn-cj btn-cj">
            ⭐ Leave Feedback
        </a>
    @endif
</div>
@endsection