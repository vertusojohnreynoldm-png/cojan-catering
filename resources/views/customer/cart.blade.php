@extends('layouts.customer')

@section('title', 'Cart — Cojan Catering')

@section('content')
<x-breadcrumbs :items="['Menu' => route('customer.menu'), 'Cart' => null]" />
<h1 class="cj-page-title">My Cart</h1>

@if(!empty($cart) && $hasMenuItems && !$orderingOpen)
    <div class="alert-cj alert-warning mb-3">
        <i class="bi bi-clock-history"></i>
        <span>We're currently closed — you can keep browsing, but checkout for menu items reopens at 7:30 AM.</span>
    </div>
@endif

@if(empty($cart))
    <div class="cj-card mt-3">
        <div class="cj-card-body text-center py-5">
            <div style="font-size:3rem;">🛒</div>
            <p style="color:var(--text-light);margin:1rem 0;">Your cart is empty.</p>
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
                            <th>Item</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cart as $id => $item)
                        <tr>
                            <td>
                                <strong>{{ $item['name'] }}</strong>
                                @if(($item['type'] ?? 'menu') === 'package')
                                    <br><small style="color:var(--text-light);">{{ $item['pax'] }} pax package</small>
                                @endif
                            </td>
                            <td>₱{{ number_format($item['price'], 2) }}</td>
                            <td>
                                <form method="POST" action="{{ route('customer.cart.update', $id) }}"
                                      x-data="{ qty: {{ $item['quantity'] }} }">
                                    @csrf
                                    <input type="hidden" name="quantity" :value="qty">
                                    <div class="cj-stepper cj-stepper-sm">
                                        <button type="button" class="cj-stepper-btn"
                                                @click="qty = Math.max(0, qty - 1); $nextTick(() => $el.closest('form').submit())"
                                                :title="qty <= 1 ? 'Remove item' : 'Decrease quantity'"
                                                :aria-label="qty <= 1 ? 'Remove item' : 'Decrease quantity'">−</button>
                                        <span class="cj-stepper-value" x-text="qty" aria-live="polite"></span>
                                        <button type="button" class="cj-stepper-btn"
                                                @click="qty++; $nextTick(() => $el.closest('form').submit())"
                                                aria-label="Increase quantity">+</button>
                                    </div>
                                </form>
                            </td>
                            <td>₱{{ number_format($item['price'] * $item['quantity'], 2) }}</td>
                            <td>
                                <a href="{{ route('customer.cart.remove', $id) }}"
                                   style="color:#e74c3c;font-size:.85rem;text-decoration:none;">✕ Remove</a>
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
        <a href="{{ route('customer.cart.clear') }}"
           style="color:#e74c3c;border:1.5px solid #e74c3c;border-radius:10px;
                  padding:8px 18px;text-decoration:none;font-size:.9rem;">
            🗑 Clear Cart
        </a>
        <a href="{{ route('customer.checkout') }}" class="btn-cj btn-cj">
            Proceed to Checkout →
        </a>
    </div>
@endif
@endsection