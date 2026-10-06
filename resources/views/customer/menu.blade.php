@extends('layouts.customer')

@section('title', 'Our Menu — Cojan Catering')

@section('content')
<x-breadcrumbs :items="isset($category) ? ['Menu' => route('customer.menu'), $category->name => null] : ['Menu' => null]" />
<h1 class="cj-page-title">Our Menu</h1>
<p class="cj-page-sub">Fresh Filipino cuisine made with love</p>

@if(!$orderingOpen)
    <div class="alert-cj alert-warning mb-3">
        <i class="bi bi-clock-history"></i>
        <span>
            We're currently closed — online ordering is available {{ $businessHoursText }} daily.
            <a href="{{ route('customer.packages.index') }}" style="color:#92400e;font-weight:600;text-decoration:underline;">
                Check out our Catering Packages, available anytime!
            </a>
        </span>
    </div>
@elseif($closesInMinutes !== null && $closesInMinutes <= 60)
    <div class="alert-cj alert-warning mb-3">
        <i class="bi bi-clock-history"></i>
        <span>Closes in {{ $closesInMinutes }} minute{{ $closesInMinutes === 1 ? '' : 's' }} — order soon!</span>
    </div>
@endif

<!-- Category Filter -->
<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="{{ route('customer.menu') }}" class="cat-pill {{ !isset($category) ? 'active' : '' }}">All ({{ $totalAvailableCount }})</a>
    @foreach($categories as $cat)
        <a href="{{ route('customer.menu.category', $cat->id) }}"
           class="cat-pill {{ isset($category) && $category->id == $cat->id ? 'active' : '' }}">
            {{ $cat->name }} ({{ $cat->menu_items_count }})
        </a>
    @endforeach
</div>

<!-- Menu Grid -->
<div class="row g-3">
    @forelse($menuItems as $item)
    <div class="col-6 col-lg-4">
        <div class="menu-card" style="{{ $orderingOpen ? '' : 'opacity:.6;' }}">
            <div class="menu-card-img">
                @if($item->image)
                    <img src="{{ asset('storage/' . $item->image) }}" alt="{{ $item->name }}">
                @else
                    <span>🍽 No Image</span>
                @endif
            </div>
            <div class="menu-card-body">
                <div class="menu-card-name">{{ $item->name }}</div>
                <div class="menu-card-desc">{{ $item->description }}</div>
                <div class="menu-card-price">₱{{ number_format($item->price, 2) }}</div>
                <form method="POST" action="{{ route('customer.cart.add') }}" x-data="{ qty: 1 }">
                    @csrf
                    <input type="hidden" name="menu_item_id" value="{{ $item->id }}">
                    <input type="hidden" name="quantity" :value="qty">
                    <div class="d-flex gap-2 align-items-center">
                        <div class="cj-stepper cj-stepper-sm">
                            <button type="button" class="cj-stepper-btn" @click="qty = Math.max(1, qty - 1)" :disabled="qty <= 1" aria-label="Decrease quantity" {{ $orderingOpen ? '' : 'disabled' }}>−</button>
                            <span class="cj-stepper-value" x-text="qty" aria-live="polite"></span>
                            <button type="button" class="cj-stepper-btn" @click="qty++" aria-label="Increase quantity" {{ $orderingOpen ? '' : 'disabled' }}>+</button>
                        </div>
                        <button type="submit" class="menu-card-btn" style="flex:1;" {{ $orderingOpen ? '' : 'disabled title="We\'re currently closed"' }}>
                            {{ $orderingOpen ? '+ Add to Cart' : 'Closed' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="cj-card">
            <div class="cj-card-body text-center py-5">
                <i class="bi bi-search" style="font-size:2.5rem;color:var(--text-light);"></i>
                <p style="color:var(--text-mid);margin:1rem 0;">
                    @if(isset($category))
                        No items found in "{{ $category->name }}" right now.
                    @else
                        No menu items available right now.
                    @endif
                </p>
                <a href="{{ route('customer.menu') }}" class="btn-cj btn-cj">View All Items</a>
            </div>
        </div>
    </div>
    @endforelse
</div>
@endsection