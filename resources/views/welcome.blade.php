<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cojan Catering Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/cojan.css') }}">
    <style>
        .hero {
            background: linear-gradient(135deg, #1a5c38 0%, #2d8653 60%, #1a5c38 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .hero-nav {
            padding: 1.25rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 10;
        }
        .hero-brand {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            color: white;
            text-decoration: none;
        }
        .hero-brand span { color: #fbbf24; }
        .hero-content {
            flex: 1;
            display: flex;
            align-items: center;
            padding: 3rem 2rem 4rem;
            position: relative;
            z-index: 5;
        }
        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.4rem, 5vw, 3.8rem);
            color: white;
            line-height: 1.15;
            margin-bottom: 1.25rem;
        }
        .hero-title em { color: #fbbf24; font-style: normal; }
        .hero-subtitle {
            font-size: 1.05rem;
            color: rgba(255,255,255,.8);
            max-width: 500px;
            margin-bottom: 2rem;
            line-height: 1.7;
        }
        .hero-emoji {
            font-size: clamp(6rem, 12vw, 10rem);
            filter: drop-shadow(0 20px 40px rgba(0,0,0,.2));
            animation: float 3.5s ease-in-out infinite;
        }
        @keyframes float {
            0%,100% { transform: translateY(0); }
            50%      { transform: translateY(-14px); }
        }
        .feature-section { background: var(--cream); padding: 5rem 2rem; }
        .feature-title {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: var(--green-dark);
            text-align: center;
            margin-bottom: .5rem;
        }
        .feature-sub { text-align: center; color: var(--text-light); margin-bottom: 3rem; }
        .feature-card {
            background: white;
            border-radius: 16px;
            padding: 2rem 1.5rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(26,92,56,.08);
            transition: all .25s ease;
            border: 1px solid rgba(26,92,56,.07);
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 40px rgba(26,92,56,.15);
            border-color: var(--green-mid);
        }
        .feature-icon {
            font-size: 2.8rem;
            margin-bottom: 1rem;
            display: block;
        }
        .feature-card h5 {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            color: var(--green-dark);
            margin-bottom: .5rem;
        }
        .feature-card p { font-size: .88rem; color: var(--text-light); margin: 0; }
        footer {
            background: var(--green-dark);
            color: rgba(255,255,255,.7);
            text-align: center;
            padding: 1.5rem;
            font-size: .85rem;
        }
        footer span { color: #fbbf24; }
    </style>
</head>
<body>
<div class="hero">
    <!-- Nav -->
    <nav class="hero-nav">
        <a href="/" class="hero-brand">🍽 Cojan <span>Catering</span></a>
        <div class="d-flex gap-2">
            <a href="{{ route('login') }}" class="btn-cj-outline btn-cj">Log In</a>
            <a href="{{ route('register') }}" class="btn-cj-amber btn-cj">Register</a>
        </div>
    </nav>

    <!-- Hero Content -->
    <div class="hero-content">
        <div class="container-fluid" style="max-width:1200px">
            <div class="row align-items-center g-4">
                <div class="col-lg-6">
                    <h1 class="hero-title">
                        Delicious Food,<br>
                        <em>Delivered</em> to You
                    </h1>
                    <p class="hero-subtitle">
                        Experience the best catering services in San Jose, Occidental Mindoro.
                        Order online, track your delivery with QR code, and enjoy fresh Filipino cuisine at your doorstep.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="{{ route('register') }}" class="btn-cj-amber btn-cj btn-cj-lg">
                            🛒 Order Now
                        </a>
                        <a href="{{ route('login') }}" class="btn-cj-outline btn-cj btn-cj-lg">
                            Log In
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center d-none d-lg-block">
                    <div class="hero-emoji">🍱</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Features -->
<section class="feature-section">
    <div class="container-fluid" style="max-width:1200px">
        <h2 class="feature-title">Why Choose Cojan Catering?</h2>
        <p class="feature-sub">Everything you need for a seamless catering experience</p>
        <div class="row g-4">
            <div class="col-sm-6 col-lg-3">
                <div class="feature-card">
                    <span class="feature-icon">🍽</span>
                    <h5>Filipino Cuisine</h5>
                    <p>Authentic Filipino dishes made with fresh, quality ingredients from local farms.</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="feature-card">
                    <span class="feature-icon">📱</span>
                    <h5>Easy Online Ordering</h5>
                    <p>Order in minutes with our simple, intuitive web-based ordering system.</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="feature-card">
                    <span class="feature-icon">📦</span>
                    <h5>QR Code Tracking</h5>
                    <p>Track your delivery in real time using our smart QR code tracking system.</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="feature-card">
                    <span class="feature-icon">🚚</span>
                    <h5>Fast Delivery</h5>
                    <p>Reliable delivery service right to your doorstep in San Jose, Occidental Mindoro.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<footer>
    © 2026 <span>Cojan Catering Services</span>. All rights reserved. | San Jose, Occidental Mindoro, Philippines
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
