@extends('layouts.customer')

@section('title', 'My Orders — Cojan Catering')

@section('content')
<x-breadcrumbs :items="['My Orders' => null]" />
<h1 class="cj-page-title">My Orders</h1>

@if($orders->isEmpty())
    <div class="cj-card mt-3">
        <div class="cj-card-body text-center py-5">
            <div style="font-size:2.5rem;">📋</div>
            <p style="color:var(--text-light);margin:1rem 0;">You have no orders yet.</p>
            <a href="{{ route('customer.menu') }}" class="btn-cj btn-cj">Browse Menu</a>
        </div>
    </div>
@else
    <div class="cj-card mt-3">
        <div class="cj-card-body p-0">
            <div class="table-responsive">
                <table class="cj-table cj-table-stack">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Delivery Time</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                        <tr>
                            <td class="cj-td-title"><strong>{{ $order->order_number }}</strong></td>
                            <td data-label="Date">{{ $order->created_at->format('M d, Y') }}</td>
                            <td data-label="{{ $order->isBooking() ? 'Event' : 'Delivery' }}">{{ $order->isBooking() ? $order->event_date->format('M d, Y h:i A') : $order->deliveryTimeLabel() }}</td>
                            <td data-label="Total">₱{{ number_format($order->total_amount, 2) }}</td>
                            <td data-label="Status">
                                <x-order-status-badge :order="$order" />
                            </td>
                            <td data-label="Payment">
                                <span class="badge-cj {{ $order->payment_status == 'paid' ? 'badge-delivered' : 'badge-pending' }}">
                                    {{ ucfirst($order->payment_status) }}
                                </span>
                            </td>
                            <td data-label="" class="cj-td-actions">
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