@extends('layouts.customer')

@section('title', 'Cart — Cojan Catering')

@section('content')
<h1 class="cj-page-title">My Cart</h1>

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
                            <td><strong>{{ $item['name'] }}</strong></td>
                            <td>₱{{ number_format($item['price'], 2) }}</td>
                            <td>
                                <form method="POST" action="{{ route('customer.cart.update', $id) }}"
                                      class="d-flex gap-1 align-items-center">
                                    @csrf
                                    <input type="number" name="quantity" value="{{ $item['quantity'] }}"
                                           min="1"
                                           style="width:60px;border:1.5px solid #ddd;border-radius:8px;
                                                  padding:4px 8px;font-size:.85rem;">
                                    <button type="submit" class="btn-cj btn-cj-sm">OK</button>
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