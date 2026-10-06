<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - Cojan Catering Admin</title>
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
    <h1 class="cj-page-title">Customer Feedback</h1>
    <p class="cj-page-sub">See what customers are saying about their orders</p>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6">
            <div class="stat-card stat-green">
                <h5>Total Feedback</h5>
                <div class="stat-num">{{ $totalFeedback }}</div>
            </div>
        </div>
        <div class="col-6">
            <div class="stat-card stat-amber">
                <h5>Average Rating</h5>
                <div class="stat-num">{{ number_format($averageRating, 1) }} ★</div>
            </div>
        </div>
    </div>

    <!-- Feedback Table -->
    <div class="cj-card">
        <div class="cj-card-header">All Feedback</div>
        <div class="cj-card-body p-0">
            @if($feedback->isEmpty())
                <p class="text-center py-4" style="color:var(--text-light)">No feedback yet.</p>
            @else
                <div class="table-responsive">
                    <table class="cj-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Order #</th>
                                <th>Rating</th>
                                <th>Comment</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($feedback as $item)
                                <tr>
                                    <td>{{ $item->user->name }}</td>
                                    <td>{{ $item->order->order_number }}</td>
                                    <td>
                                        @for($i = 1; $i <= 5; $i++)
                                            <span style="color: {{ $i <= $item->rating ? '#ffc107' : '#ddd' }}">★</span>
                                        @endfor
                                    </td>
                                    <td>{{ $item->comment ?? 'No comment' }}</td>
                                    <td>{{ $item->created_at->format('M d, Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
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
