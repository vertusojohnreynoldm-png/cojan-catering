@extends('layouts.customer')

@section('title', 'Checkout — Cojan Catering')

@section('content')
<h1 class="cj-page-title">Checkout</h1>

<div class="row g-3 mt-1">
    <!-- Order Summary -->
    <div class="col-12 col-md-5">
        <div class="cj-card">
            <div class="cj-card-header">Order Summary</div>
            <div class="cj-card-body">
                @foreach($cart as $item)
                    <div class="d-flex justify-content-between mb-2" style="font-size:.9rem;">
                        <span>{{ $item['name'] }} x{{ $item['quantity'] }}</span>
                        <span>₱{{ number_format($item['price'] * $item['quantity'], 2) }}</span>
                    </div>
                @endforeach
                <hr>
                <div class="d-flex justify-content-between mb-1" style="font-size:.9rem;">
                    <span>Subtotal</span>
                    <span>₱{{ number_format($total, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-1" style="font-size:.9rem;">
                    <span>Delivery Fee</span>
                    <span>₱50.00</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between fw-bold">
                    <span>Total</span>
                    <span style="color:var(--green-dark);">
                        ₱{{ number_format($total + 50, 2) }}
                    </span>
                </div>
                <div class="mt-3">
                    <span style="background:var(--green-dark);color:#fff;
                                 padding:4px 12px;border-radius:20px;font-size:.8rem;">
                        💵 Cash on Delivery
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Delivery Details -->
    <div class="col-12 col-md-7">
        <div class="cj-card">
            <div class="cj-card-header">Delivery Details</div>
            <div class="cj-card-body">
                @if($errors->any())
                    <div class="alert-cj alert-danger mb-3">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('customer.orders.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label style="font-weight:600;font-size:.9rem;margin-bottom:.4rem;display:block;">
                            Delivery Address <span style="color:#e74c3c;">*</span>
                        </label>
                        <textarea name="delivery_address" rows="3" required
                            style="width:100%;border:1.5px solid #ddd;border-radius:10px;
                                   padding:10px 14px;font-size:.9rem;outline:none;
                                   font-family:'DM Sans',sans-serif;resize:vertical;">{{ auth()->user()->address }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label style="font-weight:600;font-size:.9rem;margin-bottom:.4rem;display:block;">
                            Contact Number <span style="color:#e74c3c;">*</span>
                        </label>
                        <input type="text" name="delivery_phone" required
                               value="{{ auth()->user()->phone }}"
                               style="width:100%;border:1.5px solid #ddd;border-radius:10px;
                                      padding:10px 14px;font-size:.9rem;outline:none;">
                    </div>
                    <div class="mb-3">
                        <label style="font-weight:600;font-size:.9rem;margin-bottom:.4rem;display:block;">
                            Order Notes (Optional)
                        </label>
                        <textarea name="notes" rows="2"
                            placeholder="Special instructions..."
                            style="width:100%;border:1.5px solid #ddd;border-radius:10px;
                                   padding:10px 14px;font-size:.9rem;outline:none;
                                   font-family:'DM Sans',sans-serif;resize:vertical;"></textarea>
                    </div>
                    <button type="submit" class="btn-cj btn-cj w-100"
                            style="width:100%;padding:.75rem;">
                        Place Order →
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection