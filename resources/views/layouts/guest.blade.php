<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Cojan Catering') }}</title>
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Cojan Catering design system (Playfair Display + DM Sans are pulled
             in via this file's own @import) -->
        <link rel="stylesheet" href="{{ asset('css/cojan.css') }}">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="cj-auth-shell">
            <!-- Photo panel — hidden below 768px, see .cj-auth-photo in cojan.css -->
            <div class="cj-auth-photo">
                <div class="cj-auth-blob cj-auth-blob-1"></div>
                <div class="cj-auth-blob cj-auth-blob-2"></div>
                <div class="cj-auth-tagline">
                    <h2>Delicious Food,<br><em>Delivered</em> to You</h2>
                    <p>Fresh Filipino cuisine, straight from Cojan Catering to your doorstep in San Jose, Occidental Mindoro.</p>
                </div>
            </div>

            <div class="cj-auth-form-side">
                <div>
                    <a href="{{ route('welcome') }}">
                        <img src="{{ asset('images/logo-removebg-preview.png') }}" alt="Cojan Catering"
                             style="width:90px;height:90px;object-fit:contain;">
                    </a>
                </div>

                <div class="cj-card w-full mt-6 px-8 py-6" style="max-width:420px;">
                    {{ $slot }}
                </div>
            </div>
        </div>

        <!-- Needed for the logout confirmation modal (only verify-email uses it
             on this layout, but it's harmless to load here for every page). -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>
