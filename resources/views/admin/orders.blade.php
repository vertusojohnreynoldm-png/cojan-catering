<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Cojan Catering Admin</title>
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
    <h1 class="cj-page-title">All Orders</h1>
    <p class="cj-page-sub">Every order placed through Cojan Catering</p>

    <!-- Filter -->
    <form method="GET" action="{{ route('admin.orders') }}" class="d-flex gap-2 mb-4 flex-wrap">
        <select name="status" class="cj-select" style="width:auto;">
            <option value="">All Status</option>
            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
            <option value="preparing" {{ request('status') == 'preparing' ? 'selected' : '' }}>Preparing</option>
            <option value="out_for_delivery" {{ request('status') == 'out_for_delivery' ? 'selected' : '' }}>Out for Delivery</option>
            <option value="delivered" {{ request('status') == 'delivered' ? 'selected' : '' }}>Delivered</option>
            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
        </select>
        <button type="submit" class="btn-cj btn-cj btn-cj-sm">Filter</button>
        <a href="{{ route('admin.orders') }}"
           style="color:var(--green-dark);border:1.5px solid var(--green-dark);border-radius:10px;
                  padding:.3rem .75rem;text-decoration:none;font-size:.78rem;display:inline-flex;align-items:center;">
            Clear
        </a>
    </form>

    <div class="cj-card">
        <div class="cj-card-body p-0">
            <div class="table-responsive">
                <table class="cj-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td><strong>{{ $order->order_number }}</strong></td>
                                <td>{{ $order->user->name }}</td>
                                <td>₱{{ number_format($order->total_amount, 2) }}</td>
                                <td>
                                    <x-order-status-badge :order="$order" />
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                        <span class="badge-cj {{ $order->payment_status == 'paid' ? 'badge-paid' : 'badge-unpaid' }}">
                                            {{ ucfirst($order->payment_status) }}
                                        </span>
                                        <small style="color:var(--text-light);font-size:.72rem;">
                                            {{ $order->payment_method === 'gcash' ? 'GCash' : 'Cash' }}
                                        </small>
                                    </div>
                                </td>
                                <td>{{ $order->created_at->format('M d, Y') }}</td>
                                <td>
                                    <a href="{{ route('admin.orders.show', $order->id) }}" class="btn-cj btn-cj-sm">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4" style="color:var(--text-light)">No orders found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <a href="{{ route('admin.dashboard') }}"
       style="display:inline-block;margin-top:1rem;color:var(--green-dark);border:1.5px solid var(--green-dark);
              border-radius:10px;padding:8px 18px;text-decoration:none;font-size:.9rem;">
        ← Back to Dashboard
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])
</body>
</html>
