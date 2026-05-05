<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Cojan Catering</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/cojan.css') }}">
    <style>
        .dash-card {
            background: white;
            border-radius: 16px;
            padding: 1.75rem;
            border: 1px solid rgba(26,92,56,.08);
            box-shadow: 0 4px 16px rgba(26,92,56,.07);
            transition: all .25s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .dash-card:hover { transform: translateY(-4px); box-shadow: 0 12px 36px rgba(26,92,56,.14); }
        .dash-icon { font-size: 2.4rem; margin-bottom: 1rem; }
        .dash-card h5 { font-family: 'Playfair Display', serif; font-size: 1.15rem; color: var(--green-dark); margin-bottom: .4rem; }
        .dash-card p { font-size: .85rem; color: var(--text-light); flex: 1; margin-bottom: 1rem; }
        .welcome-banner {
            background: linear-gradient(135deg, var(--green-dark), var(--green-mid));
            border-radius: 16px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        .welcome-banner::after {
            content: '🍽';
            position: absolute;
            right: 2rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 4rem;
            opacity: .3;
        }
        .welcome-banner h2 { font-family: 'Playfair Display', serif; margin-bottom: .25rem; }
        .welcome-banner p { opacity: .85; margin: 0; font-size: .9rem; }
    </style>
</head>
<body>
<nav class="cj-nav">
    <a href="{{ route('customer.dashboard') }}" class="cj-nav-brand">🍽 Cojan <span>Catering</span></a>
    <div class="cj-nav-links">
        <a href="{{ route('customer.menu') }}" class="btn-cj-outline btn-cj btn-cj-sm">Menu</a>
        <a href="{{ route('customer.cart') }}" class="btn-cj-outline btn-cj btn-cj-sm">🛒 Cart</a>
        <a href="{{ route('customer.orders') }}" class="btn-cj-outline btn-cj btn-cj-sm">My Orders</a>
        <form method="POST" action="{{ route('logout') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn-cj-amber btn-cj btn-cj-sm">Logout</button>
        </form>
    </div>
</nav>

<div class="cj-page">
    <div class="welcome-banner">
        <h2>Welcome back, {{ auth()->user()->name }}! 👋</h2>
        <p>What would you like to order today?</p>
    </div>

    <div class="row g-3">
        <div class="col-sm-4">
            <div class="dash-card">
                <div class="dash-icon">🍽</div>
                <h5>Browse Menu</h5>
                <p>Explore our delicious Filipino dishes and add your favorites to the cart.</p>
                <a href="{{ route('customer.menu') }}" class="btn-cj btn-cj">Browse Menu</a>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="dash-card">
                <div class="dash-icon">📋</div>
                <h5>My Orders</h5>
                <p>View your order history and track your active deliveries in real time.</p>
                <a href="{{ route('customer.orders') }}" class="btn-cj btn-cj">View Orders</a>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="dash-card">
                <div class="dash-icon">👤</div>
                <h5>My Profile</h5>
                <p>Update your delivery address and contact information for faster checkout.</p>
                <a href="#" class="btn-cj btn-cj">View Profile</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
