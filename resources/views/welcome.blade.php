<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cojan Catering Services</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/cojan.css') }}">
    <style>
        .hero {
            background: linear-gradient(135deg, #7A2E1D 0%, #C1441E 60%, #7A2E1D 100%);
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
        .hero-brand span { color: #E8A93B; }
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
        /* Contrast fix: #E8A93B measured only 2.47:1 against the gradient's
           lightest band (#C1441E, at the 60% stop) — below the 3:1 minimum
           for large text. This lighter gold clears 3:1 there (3.08:1) and
           does better everywhere else in the gradient. */
        .hero-title em { color: #F2C265; font-style: normal; }
        .hero-subtitle {
            font-size: 1.05rem;
            color: rgba(255,255,255,.8);
            max-width: 500px;
            margin-bottom: 2rem;
            line-height: 1.7;
        }
        .hero-logo-wrap {
            display: inline-block;
            max-width: clamp(180px, 24vw, 320px);
            width: 100%;
            aspect-ratio: 1 / 1;
            position: relative;
            transition: transform .35s ease-out;
        }
        .hero-logo-wrap:hover {
            transform: rotate(-4deg) scale(1.06);
        }
        .hero-logo {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 8px 16px rgba(0,0,0,.18));
            transition: filter .35s ease-out;
            animation: float 3.5s ease-in-out infinite;
        }
        .hero-logo-wrap:hover .hero-logo {
            filter: drop-shadow(0 16px 32px rgba(0,0,0,.28));
        }
        /* Separate circular layer just for the shine sweep — kept off the
           image itself so its overflow:hidden clipping never cuts into the
           drop-shadow, which needs to render freely outside the logo's edge. */
        .hero-logo-shine {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            overflow: hidden;
            pointer-events: none;
        }
        .hero-logo-shine::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -75%;
            width: 45%;
            height: 200%;
            background: linear-gradient(
                75deg,
                rgba(255,255,255,0) 0%,
                rgba(255,255,255,0) 40%,
                rgba(255,255,255,.5) 50%,
                rgba(255,255,255,0) 60%,
                rgba(255,255,255,0) 100%
            );
            filter: blur(5px);
            transition: left 1.1s ease-in-out;
        }
        .hero-logo-wrap:hover .hero-logo-shine::after {
            left: 130%;
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
            box-shadow: 0 4px 20px rgba(122,46,29,.08);
            transition: all .25s ease;
            border: 1px solid rgba(122,46,29,.07);
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 40px rgba(122,46,29,.15);
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
        footer span { color: #E8A93B; }

        /* Scroll-reveal — the JS only ever adds .cj-reveal-target elements'
           hidden state when prefers-reduced-motion allows it, so reduced-
           motion users never see anything hidden in the first place rather
           than a hidden-then-instantly-shown flash. */
        .cj-reveal {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity .35s ease-out, transform .35s ease-out;
        }
        .cj-reveal.cj-revealed { opacity: 1; transform: translateY(0); }
        .feature-section .row > .cj-reveal:nth-child(2) { transition-delay: 80ms; }
        .feature-section .row > .cj-reveal:nth-child(3) { transition-delay: 160ms; }
        .feature-section .row > .cj-reveal:nth-child(4) { transition-delay: 240ms; }
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
                    <div class="hero-logo-wrap">
                        <img src="{{ asset('images/logo-removebg-preview.png') }}" alt="Cojan Catering logo" class="hero-logo">
                        <div class="hero-logo-shine"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Features -->
<section class="feature-section">
    <div class="container-fluid" style="max-width:1200px">
        <h2 class="feature-title cj-reveal-target">Why Choose Cojan Catering?</h2>
        <p class="feature-sub cj-reveal-target">Everything you need for a seamless catering experience</p>
        <div class="row g-4">
            <div class="col-sm-6 col-lg-3 cj-reveal-target">
                <div class="feature-card">
                    <span class="feature-icon">🍽</span>
                    <h5>Filipino Cuisine</h5>
                    <p>Authentic Filipino dishes made with fresh, quality ingredients from local farms.</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3 cj-reveal-target">
                <div class="feature-card">
                    <span class="feature-icon">📱</span>
                    <h5>Easy Online Ordering</h5>
                    <p>Order in minutes with our simple, intuitive web-based ordering system.</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3 cj-reveal-target">
                <div class="feature-card">
                    <span class="feature-icon">📦</span>
                    <h5>QR Code Tracking</h5>
                    <p>Track your delivery in real time using our smart QR code tracking system.</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3 cj-reveal-target">
                <div class="feature-card">
                    <span class="feature-icon">🚚</span>
                    <h5>Fast Delivery</h5>
                    <p>Reliable delivery service right to your doorstep in San Jose, Occidental Mindoro.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="cj-reveal-target">
    © {{ date('Y') }} <span>Cojan Catering Services</span>. All rights reserved. | San Jose, Occidental Mindoro, Philippines
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    // Reduced-motion users: never hide anything in the first place, rather
    // than hiding-then-instantly-showing on scroll.
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const targets = document.querySelectorAll('.cj-reveal-target');
    targets.forEach(el => el.classList.add('cj-reveal'));

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('cj-revealed');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.2 });

    targets.forEach(el => observer.observe(el));
})();
</script>
</body>
</html>
