<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Delivery Dashboard — Cojan Catering</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/cojan.css') }}">
    <style>
        .cj-nav { background: #92400e; }
        .cj-nav-brand span { color: #E8A93B; }
        /* Same contrast fix as .stat-amber/.stat-green in cojan.css — the
           original lighter stops failed 4.5:1 for the small uppercase label. */
        .stat-card.stat-del1 { background: linear-gradient(135deg, #b45309, #92400e); }
        .stat-card.stat-del2 { background: linear-gradient(135deg, #15803d, #14532d); }
        .cj-card-header { background: #92400e; }
        .cj-table thead tr { background: #92400e; }
        .cj-table tbody tr:hover { background: #fef3c7; }
        .btn-del { background: #d97706; color: white; border: none; padding: .45rem 1.1rem; border-radius: 6px; font-size: .85rem; font-weight: 500; cursor: pointer; transition: all .22s ease; text-decoration: none; display: inline-block; }
        .btn-del:hover { background: #b45309; color: white; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(217,119,6,.3); }
        .scan-box { background: white; border-radius: 12px; padding: 1.5rem; border: 2px dashed rgba(217,119,6,.3); margin-bottom: 1.5rem; }
        .scan-box h5 { font-family: 'Playfair Display', serif; color: #92400e; margin-bottom: 1rem; }
    </style>
</head>
<body>
<nav class="cj-nav">
    <a href="{{ route('delivery.dashboard') }}" class="cj-nav-brand">🚚 Cojan <span>Delivery</span></a>
    <div class="cj-nav-links">
        <button type="button" class="btn-cj-amber btn-cj btn-cj-sm" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</button>
    </div>
</nav>
<x-logout-modal />
<x-toast />

<div class="cj-page">
    <h1 class="cj-page-title">Welcome, {{ auth()->user()->name }}!</h1>
    <p class="cj-page-sub">Your delivery assignments for today</p>

    <div class="row g-3 mb-4">
        <div class="col-6">
            <div class="stat-card stat-del1">
                <h5>Pending Deliveries</h5>
                <div class="stat-num">{{ $pendingDeliveries }}</div>
            </div>
        </div>
        <div class="col-6">
            <div class="stat-card stat-del2">
                <h5>Completed</h5>
                <div class="stat-num">{{ $completedDeliveries }}</div>
            </div>
        </div>
    </div>

    <!-- Scan QR -->
    <div class="scan-box">
        <h5><i class="bi bi-search"></i> Find Order by Number</h5>
        <form method="POST" action="{{ route('delivery.scan') }}" class="d-flex gap-2">
            @csrf
            <input type="text" name="order_number" class="cj-input" placeholder="Enter order number (e.g. ORD-XXXXXXXX)" style="max-width:400px">
            <button type="submit" class="btn-del">Find Order</button>
        </form>
    </div>

    <!-- Deliveries -->
    <div class="cj-card">
        <div class="cj-card-header">My Deliveries</div>
        <div class="cj-card-body p-0">
            @if($deliveries->isEmpty())
                <div class="p-4 text-center" style="color:var(--text-light)">
                    No deliveries assigned yet. Check back later.
                </div>
            @else
            <div class="table-responsive">
                <table class="cj-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Address</th>
                            <th>Status</th>
                            <th>Assigned</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deliveries as $delivery)
                        <tr>
                            <td><strong>{{ $delivery->order->order_number }}</strong></td>
                            <td>{{ $delivery->order->user->name }}</td>
                            <td>{{ Str::limit($delivery->order->delivery_address, 30) }}</td>
                            <td>
                                <span class="badge-cj {{ $delivery->status === 'delivered' ? 'badge-delivered' : ($delivery->status === 'failed' ? 'badge-cancelled' : 'badge-pending') }}">
                                    {{ ucfirst(str_replace('_', ' ', $delivery->status)) }}
                                </span>
                            </td>
                            <td>{{ $delivery->assigned_at ? $delivery->assigned_at->format('M d, Y') : 'N/A' }}</td>
                            <td>
                                <a href="{{ route('delivery.orders.show', $delivery->id) }}" class="btn-del">View</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])
</body>
</html>
