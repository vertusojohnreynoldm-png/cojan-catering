<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - Cojan Catering Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
                <a href="{{ route('admin.feedback') }}" class="btn btn-outline-light btn-sm">Feedback</a>
                <a href="{{ route('admin.users') }}" class="btn btn-outline-light btn-sm">Users</a>
                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">Logout</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Customer Feedback</h2>

        <!-- Summary Cards -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card text-white bg-success mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Feedback</h5>
                        <p class="card-text display-6">{{ $totalFeedback }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card text-white bg-warning mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Average Rating</h5>
                        <p class="card-text display-6">
                            {{ number_format($averageRating, 1) }} ★
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feedback Table -->
        <div class="card mt-2">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">All Feedback</h5>
            </div>
            <div class="card-body">
                @if($feedback->isEmpty())
                    <p class="text-muted text-center">No feedback yet.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>