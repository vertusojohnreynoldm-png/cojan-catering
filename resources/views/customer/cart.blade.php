@extends('layouts.customer')

@section('title', 'Cart — Cojan Catering')

@section('content')
<x-breadcrumbs :items="['Menu' => route('customer.menu'), 'Cart' => null]" />
<h1 class="cj-page-title">My Cart</h1>

@if(auth()->user()->isBlocked())
    <x-account-restricted-notice />
@endif

@foreach($cartNotices as $notice)
    <div class="alert-cj alert-warning" role="status">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        <span>{{ $notice }}</span>
    </div>
@endforeach

@if(!empty($cart) && $hasMenuItems && !$orderingOpen)
    <div class="alert-cj alert-warning mb-3">
        <i class="bi bi-clock-history"></i>
        <span>We're currently closed — you can keep browsing, but checkout for menu items reopens at 7:30 AM.</span>
    </div>
@endif

@if(empty($cart))
    <div class="cj-card mt-3">
        <div class="cj-card-body text-center py-5">
            <div style="font-size:2.5rem;">🛒</div>
            <p style="color:var(--text-light);margin:1rem 0;">Your cart is empty.</p>
            <a href="{{ route('customer.menu') }}" class="btn-cj btn-cj">Browse Menu</a>
        </div>
    </div>
@else
    <div class="cj-card mt-3">
        <div class="cj-card-body p-0">
            <div class="table-responsive">
                {{-- Phones (≤640px): each line is a stacked card (cj-table-stack). --}}
                <table class="cj-table cj-table-stack">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th>Subtotal</th>
                            <th><span class="visually-hidden">Remove</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cart as $id => $item)
                        <tr>
                            <td class="cj-td-title">
                                @if(!empty($item['image']))
                                    <img src="{{ asset('storage/' . $item['image']) }}" alt="" class="cj-td-thumb">
                                @endif
                                <span>
                                    <strong>{{ $item['name'] }}</strong>
                                    @if(($item['type'] ?? 'menu') === 'package')
                                        <br><small style="color:var(--text-light);">{{ $item['pax'] }} pax package</small>
                                    @endif
                                </span>
                            </td>
                            <td data-label="Price">₱{{ number_format($item['price'], 2) }}</td>
                            <td data-label="Qty">
                                <form method="POST" action="{{ route('customer.cart.update', $id) }}">
                                    @csrf
                                    <x-qty-stepper :value="$item['quantity']"
                                                   :max="($item['type'] ?? 'menu') === 'package' ? null : $maxQty"
                                                   autosubmit remove-at-zero
                                                   :label="'Quantity of ' . $item['name']" />
                                </form>
                            </td>
                            <td data-label="Subtotal"><strong>₱{{ number_format($item['price'] * $item['quantity'], 2) }}</strong></td>
                            <td data-label="" class="cj-td-actions">
                                <a href="{{ route('customer.cart.remove', $id) }}" class="cj-remove-link"
                                   aria-label="Remove {{ $item['name'] }}">✕ Remove</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Subtotal</strong></td>
                            <td colspan="2"><strong>₱{{ number_format($total, 2) }}</strong></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Delivery Fee</strong></td>
                            <td colspan="2"><strong>₱50.00</strong></td>
                        </tr>
                        <tr style="background:#f4f9f6;">
                            <td colspan="3" class="text-end"><strong>Total</strong></td>
                            <td colspan="2">
                                <strong style="color:var(--green-dark);font-size:1.1rem;">
                                    ₱{{ number_format($total + 50, 2) }}
                                </strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <a href="{{ route('customer.cart.clear') }}" class="cj-clear-cart">
            🗑 Clear Cart
        </a>
        @if(auth()->user()->isBlocked())
            <button type="button" class="btn-cj btn-cj" disabled>Checkout unavailable</button>
        @else
            <a href="{{ route('customer.checkout') }}" class="btn-cj btn-cj">
                Proceed to Checkout →
            </a>
        @endif
    </div>
@endif
@endsection