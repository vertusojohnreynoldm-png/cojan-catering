@extends('layouts.customer')

@section('title', 'Feedback — Cojan Catering')

@section('styles')
<style>
    .star-rating { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 4px; }
    .star-rating input { display: none; }
    .star-rating label { font-size: 2.5rem; color: #ddd; cursor: pointer; transition: color .2s; }
    .star-rating input:checked ~ label,
    .star-rating label:hover,
    .star-rating label:hover ~ label { color: #ffc107; }
    @media (max-width: 768px) {
        .star-rating label { font-size: 2rem; }
    }
</style>
@endsection

@section('content')
<div class="d-flex align-items-center gap-2 mb-1">
    <a href="{{ route('customer.orders.show', $order->id) }}"
       style="color:var(--green-dark);text-decoration:none;font-size:.9rem;">← Back to Order</a>
</div>
<h1 class="cj-page-title">Leave Feedback</h1>
<p class="cj-page-sub">Order #{{ $order->order_number }}</p>

<div class="cj-card mt-3" style="max-width:600px;">
    <div class="cj-card-header">How was your experience? ⭐</div>
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

        <form method="POST" action="{{ route('customer.feedback.store', $order->id) }}">
            @csrf
            <div class="mb-4">
                <label style="font-weight:600;font-size:.9rem;margin-bottom:.75rem;display:block;">
                    Rating <span style="color:#e74c3c;">*</span>
                </label>
                <div class="star-rating">
                    <input type="radio" name="rating" id="star5" value="5">
                    <label for="star5">★</label>
                    <input type="radio" name="rating" id="star4" value="4">
                    <label for="star4">★</label>
                    <input type="radio" name="rating" id="star3" value="3">
                    <label for="star3">★</label>
                    <input type="radio" name="rating" id="star2" value="2">
                    <label for="star2">★</label>
                    <input type="radio" name="rating" id="star1" value="1">
                    <label for="star1">★</label>
                </div>
            </div>
            <div class="mb-3">
                <label style="font-weight:600;font-size:.9rem;margin-bottom:.4rem;display:block;">
                    Comment (Optional)
                </label>
                <textarea name="comment" rows="4"
                    placeholder="Tell us about your experience..."
                    style="width:100%;border:1.5px solid #ddd;border-radius:10px;
                           padding:10px 14px;font-size:.9rem;outline:none;
                           font-family:'DM Sans',sans-serif;resize:vertical;"></textarea>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" class="btn-cj btn-cj">Submit Feedback</button>
                <a href="{{ route('customer.orders.show', $order->id) }}"
                   style="color:var(--green-dark);border:1.5px solid var(--green-dark);
                          border-radius:10px;padding:8px 18px;text-decoration:none;font-size:.9rem;">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection