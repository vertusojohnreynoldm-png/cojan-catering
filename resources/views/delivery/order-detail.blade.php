<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Detail - Cojan Catering</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-warning">
        <div class="container">
            <a class="navbar-brand text-dark fw-bold" href="{{ route('delivery.dashboard') }}">Cojan Catering - Delivery</a>
            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-dark btn-sm">Logout</button>
            </form>
        </div>
    </nav>

    <div class="container mt-4">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <h2>Delivery Detail</h2>
        <p class="text-muted">Order #{{ $delivery->order->order_number }}</p>

        <div class="row mt-3">
            <!-- Customer Info -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Customer Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Name:</strong> {{ $delivery->order->user->name }}</p>
                        <p><strong>Phone:</strong> {{ $delivery->order->delivery_phone }}</p>
                        <p><strong>Address:</strong> {{ $delivery->order->delivery_address }}</p>
                        @if($delivery->order->notes)
                            <p><strong>Notes:</strong> {{ $delivery->order->notes }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Update Status -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Update Delivery Status</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Current Status:</strong>
                            <span class="badge bg-{{ $delivery->status == 'delivered' ? 'success' : ($delivery->status == 'failed' ? 'danger' : 'warning') }}">
                                {{ ucfirst(str_replace('_', ' ', $delivery->status)) }}
                            </span>
                        </p>
                        @if($delivery->assigned_at)
                            <p><strong>Assigned:</strong> {{ $delivery->assigned_at->format('M d, Y h:i A') }}</p>
                        @endif
                        @if($delivery->picked_up_at)
                            <p><strong>Picked Up:</strong> {{ $delivery->picked_up_at->format('M d, Y h:i A') }}</p>
                        @endif
                        @if($delivery->delivered_at)
                            <p><strong>Delivered:</strong> {{ $delivery->delivered_at->format('M d, Y h:i A') }}</p>
                        @endif

                        @if($delivery->status !== 'delivered' && $delivery->status !== 'failed')
                            <form method="POST" action="{{ route('delivery.orders.status', $delivery->id) }}">
                                @csrf
                                <div class="d-flex gap-2">
                                    <select name="status" class="form-select">
                                        <option value="assigned" {{ $delivery->status == 'assigned' ? 'selected' : '' }}>Assigned</option>
                                        <option value="picked_up" {{ $delivery->status == 'picked_up' ? 'selected' : '' }}>Picked Up</option>
                                        <option value="in_transit" {{ $delivery->status == 'in_transit' ? 'selected' : '' }}>In Transit</option>
                                        <option value="delivered" {{ $delivery->status == 'delivered' ? 'selected' : '' }}>Delivered</option>
                                        <option value="failed" {{ $delivery->status == 'failed' ? 'selected' : '' }}>Failed</option>
                                    </select>
                                    <button type="submit" class="btn btn-warning">Update</button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">Items to Deliver</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($delivery->order->orderItems as $item)
                                <tr>
                                    <td>{{ $item->menuItem->name }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>₱{{ number_format($item->subtotal, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" class="text-end"><strong>Total Amount:</strong></td>
                                <td><strong>₱{{ number_format($delivery->order->total_amount, 2) }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="alert alert-warning mt-3">
                    <strong>Payment:</strong> Cash on Delivery — Collect ₱{{ number_format($delivery->order->total_amount, 2) }} upon delivery.
                </div>
            </div>
        </div>

        <a href="{{ route('delivery.dashboard') }}" class="btn btn-outline-warning">Back to Dashboard</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>