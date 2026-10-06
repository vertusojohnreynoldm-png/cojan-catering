@extends('layouts.customer')

@section('title', 'My Profile — Cojan Catering')

@section('content')
<x-breadcrumbs :items="['My Profile' => null]" />
<h1 class="cj-page-title">My Profile</h1>
<p class="cj-page-sub">Update your account details and delivery information.</p>

<div class="row g-3 mt-1">
    <div class="col-12 col-md-8">
        @if($errors->any())
            <div class="alert-cj alert-danger mb-3">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('customer.profile.update') }}">
            @csrf
            @method('PUT')

            <div class="cj-card mb-3">
                <div class="cj-card-header">Account Details</div>
                <div class="cj-card-body">
                    <div class="mb-3">
                        <label class="cj-label">Full Name <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="name" class="cj-input"
                               value="{{ old('name', $user->name) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Email Address <span style="color:#e74c3c;">*</span></label>
                        <input type="email" name="email" class="cj-input"
                               value="{{ old('email', $user->email) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Phone Number</label>
                        <input type="text" name="phone" class="cj-input"
                               value="{{ old('phone', $user->phone) }}">
                    </div>
                    <div class="mb-0">
                        <label class="cj-label">Delivery Address</label>
                        <textarea name="address" rows="3" class="cj-textarea">{{ old('address', $user->address) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="cj-card mb-3">
                <div class="cj-card-header">Change Password</div>
                <div class="cj-card-body">
                    <p style="font-size:.82rem;color:var(--text-light);margin-bottom:1rem;">
                        Leave these fields blank if you don't want to change your password.
                    </p>
                    <div class="mb-3">
                        <label class="cj-label">Current Password</label>
                        <input type="password" name="current_password" class="cj-input"
                               autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">New Password</label>
                        <input type="password" name="password" class="cj-input"
                               autocomplete="new-password">
                    </div>
                    <div class="mb-0">
                        <label class="cj-label">Confirm New Password</label>
                        <input type="password" name="password_confirmation" class="cj-input"
                               autocomplete="new-password">
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" class="btn-cj btn-cj">Save Changes</button>
                <a href="{{ route('customer.dashboard') }}"
                   style="color:var(--green-dark);border:1.5px solid var(--green-dark);
                          border-radius:10px;padding:8px 18px;text-decoration:none;font-size:.9rem;">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
