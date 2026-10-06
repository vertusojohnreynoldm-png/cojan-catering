@extends('layouts.customer')

@section('title', 'Confirm Booking — Cojan Catering')

@section('content')
<x-breadcrumbs :items="['Catering Packages' => route('customer.packages.index'), 'Confirm Booking' => null]" />
<h1 class="cj-page-title">Confirm Your Booking</h1>

<div class="row g-3 mt-1">
    <!-- Booking Summary -->
    <div class="col-12 col-md-5">
        <div class="cj-card">
            <div class="cj-card-header">Booking Summary</div>
            <div class="cj-card-body">
                <p style="font-size:.92rem;color:var(--text-mid);margin-bottom:1rem;line-height:1.6;">
                    You're booking the <strong>{{ $package->name }}</strong> ({{ $package->pax }} pax)
                    for <strong>{{ \Illuminate\Support\Carbon::parse($booking['event_date'])->format('M d, Y \a\t h:i A') }}</strong>
                    at <strong>{{ $booking['venue'] }}</strong>.
                </p>
                <div class="d-flex justify-content-between mb-2" style="font-size:.9rem;">
                    <span>{{ $package->name }} x1</span>
                    <span>₱{{ number_format($package->price, 2) }}</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between fw-bold">
                    <span>Total</span>
                    <span style="color:var(--green-dark);">₱{{ number_format($total, 2) }}</span>
                </div>
                <p style="font-size:.78rem;color:var(--text-light);margin-top:.4rem;margin-bottom:0;">
                    No delivery fee for package bookings.
                </p>
                <div class="mt-3">
                    <span id="payment-summary-pill" style="background:var(--green-dark);color:#fff;
                                 padding:4px 12px;border-radius:20px;font-size:.8rem;">
                        💵 Cash on Delivery
                    </span>
                </div>
                <hr class="divider">
                <p style="font-size:.85rem;margin-bottom:.35rem;">
                    <strong>Contact:</strong> {{ $booking['contact_name'] }} · {{ $booking['contact_number'] }}
                </p>
                @if(!empty($booking['event_type']))
                    <p style="font-size:.85rem;margin-bottom:0;">
                        <strong>Event Type:</strong> {{ $booking['event_type'] }}
                    </p>
                @endif
            </div>
        </div>
    </div>

    <!-- Payment -->
    <div class="col-12 col-md-7">
        <div class="cj-card">
            <div class="cj-card-header">Payment</div>
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

                <form method="POST" action="{{ route('customer.packages.checkout.store') }}">
                    @csrf
                    @php $selectedPayment = old('payment_method', 'cash_on_delivery'); @endphp
                    <x-payment-method-selector
                        :selected-payment="$selectedPayment"
                        :total="$total"
                        :gcash-qr="$gcashQr" />

                    <button type="submit" class="btn-cj btn-cj w-100"
                            style="width:100%;padding:.75rem;">
                        Confirm Booking →
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
