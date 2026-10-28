@extends('layouts.customer')

@section('title', $menuItem->name . ' — Cojan Catering')

@section('content')
<x-breadcrumbs :items="array_filter([
    'Menu' => route('customer.menu'),
    ($menuItem->category?->name ?? '') => $menuItem->category ? route('customer.menu.category', $menuItem->category_id) : null,
    $menuItem->name => null,
], fn ($url, $label) => $label !== '', ARRAY_FILTER_USE_BOTH)" />

<div class="cj-card cj-item-hero">
    <div class="cj-item-hero-img">
        @if($menuItem->image)
            <img src="{{ asset('storage/' . $menuItem->image) }}" alt="{{ $menuItem->name }}">
        @else
            <span>🍽 No Image</span>
        @endif
    </div>
    <div class="cj-item-hero-body">
        @if($menuItem->category)
            <div class="cj-item-category">{{ $menuItem->category->name }}</div>
        @endif
        <h1 class="cj-page-title mb-1">{{ $menuItem->name }}</h1>
        <a href="#reviews-heading" class="cj-item-rating-link">
            <x-rating-badge :stats="$stats" />
        </a>
        @if($menuItem->description)
            <p class="cj-item-desc">{{ $menuItem->description }}</p>
        @endif
        <div class="menu-card-price">₱{{ number_format($menuItem->price, 2) }}</div>

        @if(!$menuItem->is_available)
            <div class="alert-cj alert-warning mb-0" role="status">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <span>This dish isn't available right now.</span>
            </div>
        @else
            <form method="POST" action="{{ route('customer.cart.add') }}">
                @csrf
                <input type="hidden" name="menu_item_id" value="{{ $menuItem->id }}">
                <div class="menu-card-actions">
                    <x-qty-stepper :max="$maxQty" :disabled="!$orderingOpen" :label="'Quantity of ' . $menuItem->name" />
                    <button type="submit" class="menu-card-btn" {{ $orderingOpen ? '' : 'disabled title="We\'re currently closed"' }}>
                        {{ $orderingOpen ? '+ Add to Cart' : 'Closed' }}
                    </button>
                </div>
            </form>
            @unless($orderingOpen)
                <p class="cj-item-closed">We're closed right now — online ordering is open {{ $businessHoursText }}.</p>
            @endunless
        @endif
    </div>
</div>

<x-review-list :stats="$stats" :reviews="$reviews" :breakdown="$breakdown" subject="this dish" />

<a href="{{ route('customer.menu') }}" class="cj-back-link">← Back to Menu</a>
@endsection
