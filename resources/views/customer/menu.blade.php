@extends('layouts.customer')

@section('title', 'Our Menu — Cojan Catering')

@section('content')
<h1 class="cj-page-title">Our Menu</h1>
<p class="cj-page-sub">Fresh Filipino cuisine made with love</p>

<!-- Category Filter -->
<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="{{ route('customer.menu') }}" class="cat-pill {{ !isset($category) ? 'active' : '' }}">All</a>
    @foreach($categories as $cat)
        <a href="{{ route('customer.menu.category', $cat->id) }}"
           class="cat-pill {{ isset($category) && $category->id == $cat->id ? 'active' : '' }}">
            {{ $cat->name }}
        </a>
    @endforeach
</div>

<!-- Menu Grid -->
<div class="row g-3">
    @forelse($menuItems as $item)
    <div class="col-6 col-lg-4">
        <div class="menu-card">
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
                <form method="POST" action="{{ route('customer.cart.add') }}">
                    @csrf
                    <input type="hidden" name="menu_item_id" value="{{ $item->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" class="menu-card-btn">+ Add to Cart</button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="alert-cj alert-info">No menu items available in this category.</div>
    </div>
    @endforelse
</div>
@endsection