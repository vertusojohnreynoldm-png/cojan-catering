<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('heading', 'Error') — Cojan Catering</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/cojan.css') }}">
</head>
<body style="background:var(--cream);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;">
    @php
        // Defensive: a 500 can happen before session/auth state is reliably
        // available, so this must never itself throw while rendering an
        // error page — fall back to the public welcome page if anything
        // here goes wrong.
        $destination = route('welcome');
        $ctaLabel = 'Back to Home';
        try {
            if (auth()->check()) {
                $destination = match (auth()->user()->role) {
                    'admin' => route('admin.dashboard'),
                    'delivery' => route('delivery.dashboard'),
                    default => route('customer.dashboard'),
                };
                $ctaLabel = 'Back to Dashboard';
            }
        } catch (\Throwable $e) {
            // Keep the welcome-page fallback above.
        }
    @endphp

    <div class="cj-card text-center" style="max-width:440px;width:100%;padding:2.5rem 2rem;">
        <img src="{{ asset('images/logo-removebg-preview.png') }}" alt="Cojan Catering"
             style="width:72px;height:72px;object-fit:contain;margin-bottom:1.25rem;">

        <div style="font-family:'Playfair Display',serif;font-size:3rem;font-weight:700;color:var(--green-mid);line-height:1;margin-bottom:.5rem;">
            @yield('code')
        </div>

        <h1 style="font-family:'Playfair Display',serif;font-size:1.4rem;margin-bottom:.5rem;">
            @yield('heading')
        </h1>

        <p style="color:var(--text-light);margin-bottom:1.75rem;">
            @yield('message')
        </p>

        <a href="{{ $destination }}" class="btn-cj-amber btn-cj">{{ $ctaLabel }}</a>
    </div>
</body>
</html>
