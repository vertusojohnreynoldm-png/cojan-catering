<x-guest-layout>
    <a href="{{ route('welcome') }}" class="cj-auth-back-pill">
        <i class="bi bi-arrow-left"></i> {{ __('Back to Home') }}
    </a>

    <div class="text-center mb-4">
        <div style="font-size:2.5rem;line-height:1;margin-bottom:.5rem;">📧</div>
        <h2 class="fw-serif" style="font-size:1.3rem;color:var(--green-dark);margin-bottom:.4rem;">
            {{ __('Verify your email') }}
        </h2>
        <p style="font-size:.9rem;color:var(--text-mid);margin:0;">
            {{ __('Before you can place orders or book catering packages, please confirm your email address by clicking the link we just sent you.') }}
        </p>
    </div>

    @if (session('success'))
        <div class="alert-cj alert-success mb-3" style="font-size:.85rem;">
            {{ session('success') }}
        </div>
    @endif

    <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:.75rem;">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>
                {{ __('Resend Verification Email') }}
            </x-primary-button>
        </form>

        <button type="button" class="cj-auth-link" style="background:none;border:none;padding:0;cursor:pointer;"
                data-bs-toggle="modal" data-bs-target="#logoutModal">
            {{ __('Log Out') }}
        </button>
    </div>

    <x-logout-modal />
</x-guest-layout>
