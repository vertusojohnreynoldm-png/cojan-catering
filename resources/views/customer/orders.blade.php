@extends('layouts.customer')

@section('title', 'My Orders — Cojan Catering')

@section('content')
<h1 class="cj-page-title">My Orders</h1>

@if($orders->isEmpty())
    <div class="cj-card mt-3">
        <div class="cj-card-body text-center py-5">
            <div style="font-size:3rem;">📋</div>
            <p style="color:var(--text-light);margin:1rem 0;">You have no orders yet.</p>
            <a href="{{ route('customer.menu') }}" class="btn-cj btn-cj">Browse Menu</a>
        </div>
    </div>
@else
    <div class="cj-card mt-3">
        <div class="cj-card-body p-0">
            <div class="table-responsive">
                <table class="cj-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                        <tr>
                            <td><strong>{{ $order->order_number }}</strong></td>
                            <td>{{ $order->created_at->format('M d, Y') }}</td>
                            <td>₱{{ number_format($order->total_amount, 2) }}</td>
                            <td>
                                <span class="badge-cj badge-{{ $order->status === 'delivered' ? 'delivered' : ($order->status === 'cancelled' ? 'cancelled' : ($order->status === 'out_for_delivery' ? 'delivery' : ($order->status === 'preparing' ? 'preparing' : ($order->status === 'confirmed' ? 'confirmed' : 'pending')))) }}">
                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge-cj {{ $order->payment_status == 'paid' ? 'badge-delivered' : 'badge-pending' }}">
                                    {{ ucfirst($order->payment_status) }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('customer.orders.show', $order->id) }}"
                                   class="btn-cj btn-cj-sm">View</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
@endsection