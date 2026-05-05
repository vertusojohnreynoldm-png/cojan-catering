<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Cojan Catering Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="{{ route('admin.dashboard') }}">Cojan Catering - Admin</a>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.orders') }}" class="btn btn-outline-light btn-sm">Orders</a>
                <a href="{{ route('admin.menu') }}" class="btn btn-outline-light btn-sm">Menu</a>
                <a href="{{ route('admin.inventory') }}" class="btn btn-outline-light btn-sm">Inventory</a>
                <a href="{{ route('admin.analytics') }}" class="btn btn-outline-light btn-sm">Analytics</a>
                <a href="{{ route('admin.users') }}" class="btn btn-outline-light btn-sm">Users</a>
                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">Logout</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Analytics</h2>

        <!-- Summary Cards -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Orders</h5>
                        <p class="card-text display-6">{{ $totalOrders }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Revenue</h5>
                        <p class="card-text display-6">₱{{ number_format($totalRevenue, 0) }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Customers</h5>
                        <p class="card-text display-6">{{ $totalCustomers }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Avg Order Value</h5>
                        <p class="card-text display-6">₱{{ number_format($avgOrderValue, 0) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="row mt-2">
            <!-- Orders Per Day -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Orders Per Day (Last 7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="ordersChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Revenue Per Day -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Revenue Per Day (Last 7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="row">
            <!-- Top Selling Items -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Top 5 Selling Items</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="topItemsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Orders By Status -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Orders By Status</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Orders Per Day Chart
        const ordersCtx = document.getElementById('ordersChart').getContext('2d');
        new Chart(ordersCtx, {
            type: 'bar',
            data: {
                labels: {!! json_encode($ordersPerDay->pluck('date')) !!},
                datasets: [{
                    label: 'Orders',
                    data: {!! json_encode($ordersPerDay->pluck('total')) !!},
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });

        // Revenue Per Day Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode($revenuePerDay->pluck('date')) !!},
                datasets: [{
                    label: 'Revenue (₱)',
                    data: {!! json_encode($revenuePerDay->pluck('total')) !!},
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });

        // Top Items Chart
        const topItemsCtx = document.getElementById('topItemsChart').getContext('2d');
        new Chart(topItemsCtx, {
            type: 'bar',
            data: {
                labels: {!! json_encode($topItems->map(fn($i) => $i->menuItem ? $i->menuItem->name : 'Unknown')) !!},
                datasets: [{
                    label: 'Units Sold',
                    data: {!! json_encode($topItems->pluck('total_sold')) !!},
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });

        // Orders By Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($ordersByStatus->pluck('status')->map(fn($s) => ucfirst(str_replace('_', ' ', $s)))) !!},
                datasets: [{
                    data: {!! json_encode($ordersByStatus->pluck('total')) !!},
                    backgroundColor: [
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(75, 192, 100, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                    ],
                    borderWidth: 1
                }]
            },
            options: { responsive: true }
        });
    </script>
</body>
</html>