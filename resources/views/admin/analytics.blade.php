<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Cojan Catering Admin</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/cojan.css') }}">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<x-admin-nav />
<x-toast />

<div class="cj-page">
    <h1 class="cj-page-title">Analytics</h1>
    <p class="cj-page-sub">Business performance at a glance</p>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-blue">
                <h5>Total Orders</h5>
                <div class="stat-num">{{ $totalOrders }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-green">
                <h5>Total Revenue</h5>
                <div class="stat-num" style="font-size:1.7rem">₱{{ number_format($totalRevenue, 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-teal">
                <h5>Total Customers</h5>
                <div class="stat-num">{{ $totalCustomers }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-amber">
                <h5>Avg Order Value</h5>
                <div class="stat-num" style="font-size:1.7rem">₱{{ number_format($avgOrderValue, 0) }}</div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <div class="cj-card h-100">
                <div class="cj-card-header">Orders Per Day (Last 7 Days)</div>
                <div class="cj-card-body">
                    <div style="height:280px;">
                        <canvas id="ordersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="cj-card h-100">
                <div class="cj-card-header">Revenue Per Day (Last 7 Days)</div>
                <div class="cj-card-body">
                    <div style="height:280px;">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row g-3 mt-1">
        <div class="col-12 col-md-6">
            <div class="cj-card h-100">
                <div class="cj-card-header">Top 5 Selling Items</div>
                <div class="cj-card-body">
                    <div style="height:280px;">
                        <canvas id="topItemsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="cj-card h-100">
                <div class="cj-card-header">Orders By Status</div>
                <div class="cj-card-body">
                    <div style="height:280px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
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
<script>
    // Orders Per Day Chart — chili-red, matching .btn-cj / .green-mid elsewhere
    const ordersCtx = document.getElementById('ordersChart').getContext('2d');
    new Chart(ordersCtx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($ordersPerDay->pluck('date')) !!},
            datasets: [{
                label: 'Orders',
                data: {!! json_encode($ordersPerDay->pluck('total')) !!},
                backgroundColor: 'rgba(193, 68, 30, 0.75)',
                borderColor: '#C1441E',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // Revenue Per Day Chart — turmeric-amber, deliberately distinct from the
    // orders chart's chili-red so the two Row 1 charts read apart at a glance
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: {!! json_encode($revenuePerDay->pluck('date')) !!},
            datasets: [{
                label: 'Revenue (₱)',
                data: {!! json_encode($revenuePerDay->pluck('total')) !!},
                backgroundColor: 'rgba(232, 169, 59, 0.25)',
                borderColor: '#E8A93B',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#E8A93B'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } }
        }
    });

    // Top Items Chart — 5-tone warm sequence (chili, amber, terracotta, plus
    // two supporting earth tones for separation beyond the core 3 brand colors)
    const topItemsCtx = document.getElementById('topItemsChart').getContext('2d');
    new Chart(topItemsCtx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($topItems->map(fn($i) => $i->menuItem ? $i->menuItem->name : 'Unknown')) !!},
            datasets: [{
                label: 'Units Sold',
                data: {!! json_encode($topItems->pluck('total_sold')) !!},
                backgroundColor: [
                    '#C1441E', // chili
                    '#E8A93B', // turmeric
                    '#7A2E1D', // terracotta
                    '#B5651D', // burnt sienna
                    '#6B4226', // deep coffee
                ],
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // Orders By Status Chart — reuses the exact colors already used for each
    // status's badge elsewhere in the app (badge-pending, badge-confirmed,
    // etc.), so a given status is the same color here as everywhere else.
    // Mapped by the raw status string (not array position), since
    // ordersByStatus is grouped from whichever statuses exist in the DB and
    // isn't guaranteed to come back in a fixed order.
    const statusColorMap = {
        pending: '#92400e',
        confirmed: '#1e40af',
        preparing: '#5b21b6',
        out_for_delivery: '#9d174d',
        delivered: '#065f46',
        cancelled: '#991b1b',
    };
    const statusRaw = {!! json_encode($ordersByStatus->pluck('status')) !!};
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: {!! json_encode($ordersByStatus->pluck('status')->map(fn($s) => ucfirst(str_replace('_', ' ', $s)))) !!},
            datasets: [{
                data: {!! json_encode($ordersByStatus->pluck('total')) !!},
                backgroundColor: statusRaw.map(s => statusColorMap[s] || '#94a3b8'),
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
</script>
</body>
</html>
