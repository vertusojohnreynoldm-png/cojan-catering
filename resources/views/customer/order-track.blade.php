@extends('layouts.customer')

@section('title', 'Track Order — Cojan Catering')

@section('styles')
<style>
    .track-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        position: relative;
    }
    .track-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .85rem;
        font-weight: 700;
        margin-bottom: .5rem;
        z-index: 1;
    }
    .track-circle.done {
        background: var(--green-dark);
        color: #fff;
    }
    .track-circle.pending {
        background: #f0f0f0;
        color: #aaa;
        border: 2px solid #ddd;
    }
    .track-label {
        font-size: .72rem;
        text-align: center;
        color: var(--text-light);
        max-width: 60px;
    }
    .track-label.done { color: var(--green-dark); font-weight: 600; }
    .track-line {
        flex-grow: 1;
        height: 3px;
        background: #ddd;
        margin-top: -1.75rem;
        position: relative;
        top: 20px;
        z-index: 0;
    }
    .track-line.done { background: var(--green-dark); }
    @media (max-width: 480px) {
        .track-circle { width: 32px; height: 32px; font-size: .75rem; }
        .track-label { font-size: .65rem; max-width: 48px; }
    }
</style>
@endsection

@section('content')
<h1 class="cj-page-title">Order Tracking</h1>
<p class="cj-page-sub">Order #{{ $order->order_number }}</p>

@php
    $statuses = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered'];
    $currentIndex = array_search($order->status, $statuses);
@endphp

<!-- Status Timeline -->
<div class="cj-card mb-3">
    <div class="cj-card-header">Delivery Status</div>
    <div class="cj-card-body">
        <div class="d-flex align-items-flex-start justify-content-between mt-2">
            @foreach($statuses as $index => $status)
                <div class="track-step">
                    <div class="track-circle {{ $index <= $currentIndex ? 'done' : 'pending' }}">
                        {{ $index <= $currentIndex ? '✓' : $index + 1 }}
                    </div>
                    <div class="track-label {{ $index <= $currentIndex ? 'done' : '' }}">
                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                    </div>
                </div>
                @if(!$loop->last)
                    <div class="track-line {{ $index < $currentIndex ? 'done' : '' }}"></div>
                @endif
            @endforeach
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Order Info -->
    <div class="col-12 col-md-6">
        <div class="cj-card h-100">
            <div class="cj-card-header">Order Information</div>
            <div class="cj-card-body">
                <table style="width:100%;font-size:.9rem;border-collapse:collapse;">
                    <tr>
                        <td style="padding:6px 0;color:var(--text-light);width:40%;">Order #</td>
                        <td style="padding:6px 0;font-weight:600;">{{ $order->order_number }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-light);">Address</td>
                        <td style="padding:6px 0;">{{ $order->delivery_address }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-light);">Contact</td>
                        <td style="padding:6px 0;">{{ $order->delivery_phone }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-light);">Total</td>
                        <td style="padding:6px 0;font-weight:600;color:var(--green-dark);">
                            ₱{{ number_format($order->total_amount, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-light);">Payment</td>
                        <td style="padding:6px 0;">💵 Cash on Delivery</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Order Items -->
    <div class="col-12 col-md-6">
        <div class="cj-card h-100">
            <div class="cj-card-header">Items Ordered</div>
            <div class="cj-card-body p-0">
                <div class="table-responsive">
                    <table class="cj-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->orderItems as $item)
                            <tr>
                                <td>{{ $item->menuItem->name }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>₱{{ number_format($item->subtotal, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection