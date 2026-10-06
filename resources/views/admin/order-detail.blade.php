<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Detail - Cojan Catering Admin</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/cojan.css') }}">
</head>
<body>
<x-admin-nav />
<x-toast />

<div class="cj-page">
    <h1 class="cj-page-title">{{ $order->isBooking() ? '📦 Booking Detail' : 'Order Detail' }}</h1>
    <p class="cj-page-sub">Order #{{ $order->order_number }}</p>

    @if($order->event_date)
    <!-- Event Booking Details — package bookings only -->
    <div class="cj-card mb-3">
        <div class="cj-card-header amber">📦 Event Booking Details</div>
        <div class="cj-card-body">
            <div class="row g-2">
                <div class="col-12 col-md-4">
                    <p class="mb-0"><strong>Event Date &amp; Time:</strong><br>{{ $order->event_date->format('M d, Y \a\t h:i A') }}</p>
                </div>
                <div class="col-12 col-md-4">
                    <p class="mb-0"><strong>Venue:</strong><br>{{ $order->delivery_address }}</p>
                </div>
                <div class="col-12 col-md-4">
                    <p class="mb-0">
                        <strong>Booking Contact:</strong><br>
                        {{ $order->contact_name }} · {{ $order->delivery_phone }}
                        @if($order->event_type)
                            <br><span class="badge-cj badge-pending">{{ $order->event_type }}</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="row g-3">
        <!-- Order Info -->
        <div class="col-12 col-md-6">
            <div class="cj-card h-100">
                <div class="cj-card-header">Order Information</div>
                <div class="cj-card-body">
                    <p><strong>Customer:</strong> {{ $order->user->name }}</p>
                    <p><strong>Email:</strong> {{ $order->user->email }}</p>
                    <p><strong>Phone:</strong> {{ $order->delivery_phone }}</p>
                    <p><strong>Address:</strong> {{ $order->delivery_address }}</p>
                    <p><strong>Date:</strong> {{ $order->created_at->format('M d, Y h:i A') }}</p>
                    @if($order->notes)
                        <p><strong>Notes:</strong> {{ $order->notes }}</p>
                    @endif
                    <hr class="divider">
                    <p><strong>Payment Method:</strong> {{ $order->payment_method === 'gcash' ? '📱 GCash' : '💵 Cash on Delivery' }}</p>
                    @if($order->payment_method === 'gcash' && $order->gcash_reference)
                        <p><strong>GCash Reference:</strong> <code>{{ $order->gcash_reference }}</code></p>
                    @endif
                    <p>
                        <strong>Payment Status:</strong>
                        <span class="badge-cj {{ $order->payment_status == 'paid' ? 'badge-paid' : 'badge-unpaid' }}">
                            {{ ucfirst($order->payment_status) }}
                        </span>
                    </p>
                    @if($order->payment_status !== 'paid')
                        <form method="POST" action="{{ route('admin.orders.mark-paid', $order->id) }}">
                            @csrf
                            <button type="submit" class="btn-cj-amber btn-cj btn-cj-sm">Mark as Paid</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <!-- Update Status -->
        <div class="col-12 col-md-6">
            <div class="cj-card h-100">
                <div class="cj-card-header">Update Status</div>
                <div class="cj-card-body">
                    <p><strong>Current Status:</strong>
                        <x-order-status-badge :order="$order" />
                    </p>

                    @if($order->isBooking())
                        {{-- Bookings get a reduced, booking-appropriate set of
                             actions — no 'preparing'/'out_for_delivery', no
                             delivery-personnel assignment, since there's no
                             delivery being tracked mid-route for an event. --}}
                        @if($order->status === 'pending')
                            <form method="POST" action="{{ route('admin.orders.status', $order->id) }}">
                                @csrf
                                <input type="hidden" name="status" value="confirmed">
                                <button type="submit" class="btn-cj-amber btn-cj">✓ Confirm Booking</button>
                            </form>
                        @elseif($order->status === 'confirmed')
                            <div class="d-flex gap-2 flex-wrap">
                                <form method="POST" action="{{ route('admin.orders.status', $order->id) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="delivered">
                                    <button type="submit" class="btn-cj btn-cj">✓ Mark Completed</button>
                                </form>
                                <form method="POST" action="{{ route('admin.orders.status', $order->id) }}"
                                      onsubmit="return confirm('Cancel this booking?');">
                                    @csrf
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="btn-cj-danger btn-cj">✕ Cancel Booking</button>
                                </form>
                            </div>
                        @else
                            <p style="color:var(--text-light);font-size:.88rem;margin-bottom:0;">
                                This booking is {{ strtolower($order->statusLabel()) }} — no further actions available.
                            </p>
                        @endif
                    @else
                        <form method="POST" action="{{ route('admin.orders.status', $order->id) }}">
                            @csrf
                            <div class="d-flex gap-2">
                                <select name="status" class="cj-select">
                                    <option value="pending" {{ $order->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="confirmed" {{ $order->status == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                                    <option value="preparing" {{ $order->status == 'preparing' ? 'selected' : '' }}>Preparing</option>
                                    <option value="out_for_delivery" {{ $order->status == 'out_for_delivery' ? 'selected' : '' }}>Out for Delivery</option>
                                    <option value="delivered" {{ $order->status == 'delivered' ? 'selected' : '' }}>Delivered</option>
                                    <option value="cancelled" {{ $order->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </select>
                                <button type="submit" class="btn-cj btn-cj">Update</button>
                            </div>
                        </form>

                        <!-- Assign Delivery -->
                        @if($deliveryPersonnel->count() > 0)
                            <hr class="divider">
                            <p><strong>Assign Delivery Personnel:</strong></p>
                            <form method="POST" action="{{ route('admin.orders.assign', $order->id) }}">
                                @csrf
                                <div class="d-flex gap-2">
                                    <select name="user_id" class="cj-select">
                                        @foreach($deliveryPersonnel as $personnel)
                                            <option value="{{ $personnel->id }}"
                                                {{ $order->delivery && $order->delivery->user_id == $personnel->id ? 'selected' : '' }}>
                                                {{ $personnel->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn-cj-amber btn-cj">Assign</button>
                                </div>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code -->
    <div class="cj-card mt-3">
        <div class="cj-card-header">QR Code</div>
        <div class="cj-card-body text-center py-4">
            @if($order->qr_code)
                <div style="display:inline-block;padding:16px;background:#fff;
                            border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.08);">
                    {!! $order->qr_code !!}
                </div>
                <p style="color:var(--text-light);font-size:.8rem;margin-top:1rem;">Scan to track this order</p>
            @else
                <p style="color:var(--text-light);">QR Code not available</p>
            @endif
        </div>
    </div>

    <!-- Order Items -->
    <div class="cj-card mt-3">
        <div class="cj-card-header">Order Items</div>
        <div class="cj-card-body p-0">
            <div class="table-responsive">
                <table class="cj-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Price</th>
                            <th>Quantity</th>
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
                            <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                            <td><strong>₱{{ number_format($order->subtotal, 2) }}</strong></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Delivery Fee:</strong></td>
                            <td><strong>₱{{ number_format($order->delivery_fee, 2) }}</strong></td>
                        </tr>
                        <tr style="background:#f4f9f6;">
                            <td colspan="3" class="text-end"><strong>Total:</strong></td>
                            <td><strong style="color:var(--green-dark);">₱{{ number_format($order->total_amount, 2) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <a href="{{ $order->isBooking() ? route('admin.bookings') : route('admin.orders') }}"
       style="display:inline-block;margin-top:1rem;color:var(--green-dark);border:1.5px solid var(--green-dark);
              border-radius:10px;padding:8px 18px;text-decoration:none;font-size:.9rem;">
        ← Back to {{ $order->isBooking() ? 'Bookings' : 'Orders' }}
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])
</body>
</html>
