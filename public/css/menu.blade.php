<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Menu — Cojan Catering</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/cojan.css') }}">
</head>
<body>
<nav class="cj-nav">
    <a href="{{ route('customer.dashboard') }}" class="cj-nav-brand">🍽 Cojan <span>Catering</span></a>
    <div class="cj-nav-links">
        <a href="{{ route('customer.cart') }}" class="btn-cj-outline btn-cj btn-cj-sm">🛒 Cart</a>
        <a href="{{ route('customer.orders') }}" class="btn-cj-outline btn-cj btn-cj-sm">My Orders</a>
        <form method="POST" action="{{ route('logout') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn-cj-amber btn-cj btn-cj-sm">Logout</button>
        </form>
    </div>
</nav>

<div class="cj-page">
    @if(session('success'))
        <div class="alert-cj alert-success">✅ {{ session('success') }}</div>
    @endif

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
        <div class="col-sm-6 col-lg-4">
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
