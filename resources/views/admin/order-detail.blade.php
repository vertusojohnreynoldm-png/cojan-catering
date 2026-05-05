<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Detail - Cojan Catering Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="{{ route('admin.dashboard') }}">Cojan Catering - Admin</a>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.orders') }}" class="btn btn-outline-light btn-sm">Orders</a>
                <a href="{{ route('admin.menu') }}" class="btn btn-outline-light btn-sm">Menu</a>
                <a href="{{ route('admin.users') }}" class="btn btn-outline-light btn-sm">Users</a>
                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">Logout</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <h2>Order Detail</h2>
        <p class="text-muted">Order #{{ $order->order_number }}</p>

        <div class="row mt-3">
            <!-- Order Info -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Order Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Customer:</strong> {{ $order->user->name }}</p>
                        <p><strong>Email:</strong> {{ $order->user->email }}</p>
                        <p><strong>Phone:</strong> {{ $order->delivery_phone }}</p>
                        <p><strong>Address:</strong> {{ $order->delivery_address }}</p>
                        <p><strong>Date:</strong> {{ $order->created_at->format('M d, Y h:i A') }}</p>
                        @if($order->notes)
                            <p><strong>Notes:</strong> {{ $order->notes }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Update Status -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Update Status</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Current Status:</strong>
                            <span class="badge bg-{{ $order->status == 'delivered' ? 'success' : ($order->status == 'cancelled' ? 'danger' : 'warning') }}">
                                {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                            </span>
                        </p>
                        <form method="POST" action="{{ route('admin.orders.status', $order->id) }}">
                            @csrf
                            <div class="d-flex gap-2">
                                <select name="status" class="form-select">
                                    <option value="pending" {{ $order->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="confirmed" {{ $order->status == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                                    <option value="preparing" {{ $order->status == 'preparing' ? 'selected' : '' }}>Preparing</option>
                                    <option value="out_for_delivery" {{ $order->status == 'out_for_delivery' ? 'selected' : '' }}>Out for Delivery</option>
                                    <option value="delivered" {{ $order->status == 'delivered' ? 'selected' : '' }}>Delivered</option>
                                    <option value="cancelled" {{ $order->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </select>
                                <button type="submit" class="btn btn-dark">Update</button>
                            </div>
                        </form>

                        <!-- Assign Delivery -->
                        @php
                            $deliveryPersonnel = App\Models\User::where('role', 'delivery')->get();
                        @endphp
                        @if($deliveryPersonnel->count() > 0)
                            <hr>
                            <p><strong>Assign Delivery Personnel:</strong></p>
                            <form method="POST" action="{{ route('admin.orders.assign', $order->id) }}">
                                @csrf
                                <div class="d-flex gap-2">
                                    <select name="user_id" class="form-select">
                                        @foreach($deliveryPersonnel as $personnel)
                                            <option value="{{ $personnel->id }}"
                                                {{ $order->delivery && $order->delivery->user_id == $personnel->id ? 'selected' : '' }}>
                                                {{ $personnel->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-warning">Assign</button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- QR Code -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">QR Code</h5>
            </div>
            <div class="card-body text-center">
                @if($order->qr_code)
                    {!! $order->qr_code !!}
                    <p class="text-muted small mt-2">Scan to track this order</p>
                @else
                    <p class="text-muted">QR Code not available</p>
                @endif
            </div>
        </div>

        <!-- Order Items -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Order Items</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
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
                                    <td>{{ $item->menuItem->name }}</td>
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
                            <tr>
                                <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                <td><strong>₱{{ number_format($order->total_amount, 2) }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <a href="{{ route('admin.orders') }}" class="btn btn-outline-dark">Back to Orders</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>