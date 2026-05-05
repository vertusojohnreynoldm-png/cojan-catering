<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Cojan Catering</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/cojan.css') }}">
</head>
<body>
<nav class="cj-nav">
    <a href="{{ route('admin.dashboard') }}" class="cj-nav-brand">🍽 Cojan <span>Admin</span></a>
    <div class="cj-nav-links">
        <a href="{{ route('admin.orders') }}" class="btn-cj-outline btn-cj btn-cj-sm">Orders</a>
        <a href="{{ route('admin.menu') }}" class="btn-cj-outline btn-cj btn-cj-sm">Menu</a>
        <a href="{{ route('admin.inventory') }}" class="btn-cj-outline btn-cj btn-cj-sm">Inventory</a>
        <a href="{{ route('admin.analytics') }}" class="btn-cj-outline btn-cj btn-cj-sm">Analytics</a>
        <a href="{{ route('admin.feedback') }}" class="btn-cj-outline btn-cj btn-cj-sm">Feedback</a>
        <a href="{{ route('admin.users') }}" class="btn-cj-outline btn-cj btn-cj-sm">Users</a>
        <form method="POST" action="{{ route('logout') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn-cj-amber btn-cj btn-cj-sm">Logout</button>
        </form>
    </div>
</nav>

<div class="cj-page">
    <h1 class="cj-page-title">Welcome back, {{ auth()->user()->name }}!</h1>
    <p class="cj-page-sub">Here's what's happening with your catering business today.</p>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-blue">
                <h5>Total Orders</h5>
                <div class="stat-num">{{ $totalOrders }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-amber">
                <h5>Pending Orders</h5>
                <div class="stat-num">{{ $pendingOrders }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-green">
                <h5>Total Customers</h5>
                <div class="stat-num">{{ $totalCustomers }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-teal">
                <h5>Total Revenue</h5>
                <div class="stat-num" style="font-size:1.7rem">₱{{ number_format($totalRevenue, 0) }}</div>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="cj-card">
        <div class="cj-card-header d-flex justify-content-between align-items-center">
            <span>Recent Orders</span>
            <a href="{{ route('admin.orders') }}" class="btn-cj-amber btn-cj btn-cj-sm">View All</a>
        </div>
        <div class="cj-card-body p-0">
            <div class="table-responsive">
                <table class="cj-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                        <tr>
                            <td><strong>{{ $order->order_number }}</strong></td>
                            <td>{{ $order->user->name }}</td>
                            <td>₱{{ number_format($order->total_amount, 2) }}</td>
                            <td>
                                <span class="badge-cj badge-{{ $order->status === 'delivered' ? 'delivered' : ($order->status === 'cancelled' ? 'cancelled' : ($order->status === 'out_for_delivery' ? 'delivery' : ($order->status === 'preparing' ? 'preparing' : ($order->status === 'confirmed' ? 'confirmed' : 'pending')))) }}">
                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                </span>
                            </td>
                            <td>{{ $order->created_at->format('M d, Y') }}</td>
                            <td>
                                <a href="{{ route('admin.orders.show', $order->id) }}" class="btn-cj btn-cj-sm">View</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center py-4" style="color:var(--text-light)">No orders yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
