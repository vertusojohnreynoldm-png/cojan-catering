@extends('layouts.customer')

@section('title', 'Feedback — Cojan Catering')

@section('styles')
<style>
    .star-rating { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 4px; }
    /* Visually hidden but still focusable/tabbable — display:none removes an
       input from the tab order entirely, which made this picker impossible
       to operate by keyboard. */
    .star-rating input {
        position: absolute;
        width: 1px; height: 1px;
        padding: 0; margin: -1px;
        overflow: hidden;
        clip: rect(0,0,0,0);
        white-space: nowrap;
        border: 0;
    }
    .star-rating label { font-size: 2.5rem; color: #ddd; cursor: pointer; transition: var(--transition); }
    .star-rating input:checked ~ label,
    .star-rating label:hover,
    .star-rating label:hover ~ label { color: #ffc107; }
    .star-rating input:focus-visible + label {
        outline: 2px solid var(--green-mid);
        outline-offset: 3px;
        border-radius: 4px;
    }
    @media (max-width: 768px) {
        .star-rating label { font-size: 2rem; }
    }
</style>
@endsection

@section('content')
<x-breadcrumbs :items="[
    'My Orders' => route('customer.orders'),
    'Order #' . $order->order_number => route('customer.orders.show', $order->id),
    'Leave Feedback' => null,
]" />
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
            <fieldset class="mb-4" style="border:none;padding:0;margin:0 0 1.5rem;">
                <legend style="font-weight:600;font-size:.9rem;margin-bottom:.75rem;padding:0;">
                    Rating <span style="color:#e74c3c;">*</span>
                </legend>
                <div class="star-rating">
                    <input type="radio" name="rating" id="star5" value="5" aria-label="5 stars">
                    <label for="star5">★</label>
                    <input type="radio" name="rating" id="star4" value="4" aria-label="4 stars">
                    <label for="star4">★</label>
                    <input type="radio" name="rating" id="star3" value="3" aria-label="3 stars">
                    <label for="star3">★</label>
                    <input type="radio" name="rating" id="star2" value="2" aria-label="2 stars">
                    <label for="star2">★</label>
                    <input type="radio" name="rating" id="star1" value="1" aria-label="1 star">
                    <label for="star1">★</label>
                </div>
            </fieldset>
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