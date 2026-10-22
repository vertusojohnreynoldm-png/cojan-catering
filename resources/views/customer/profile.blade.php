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
                        <label class="cj-label">Full Name <span style="color:var(--required);">*</span></label>
                        <input type="text" name="name" class="cj-input"
                               value="{{ old('name', $user->name) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Email Address <span style="color:var(--required);">*</span></label>
                        <input type="email" name="email" class="cj-input"
                               value="{{ old('email', $user->email) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Phone Number</label>
                        <input type="tel" name="phone" id="profile-phone" class="cj-input"
                               inputmode="numeric" maxlength="13"
                               value="{{ old('phone', $user->phone) }}">
                        <small id="profile-phone-hint" style="font-size:.78rem;color:var(--text-light);display:block;margin-top:.3rem;">
                            Format: 09XXXXXXXXX
                        </small>
                        @error('phone')
                            <small style="color:var(--danger);font-size:.78rem;display:block;margin-top:.3rem;">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Default Delivery Area (Optional)</label>
                        <p style="font-size:.78rem;color:var(--text-light);margin:0 0 .5rem;">
                            Saved here just to pre-fill checkout faster — this isn't checked against delivery areas until you actually place an order or book catering.
                        </p>
                        <div class="row g-2">
                            <div class="col-12 col-sm-4">
                                <select name="province" id="profile-province" class="cj-select">
                                    <option value="">Province...</option>
                                    @foreach(array_keys($allAreas) as $provinceOption)
                                        <option value="{{ $provinceOption }}" {{ old('province', $user->province) === $provinceOption ? 'selected' : '' }}>{{ $provinceOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-sm-4">
                                <select name="municipality" id="profile-municipality" class="cj-select">
                                    <option value="">Municipality/City...</option>
                                </select>
                                @error('municipality')
                                    <small style="color:var(--danger);font-size:.78rem;display:block;margin-top:.3rem;">{{ $message }}</small>
                                @enderror
                            </div>
                            <div class="col-12 col-sm-4">
                                <select name="barangay" id="profile-barangay" class="cj-select">
                                    <option value="">Barangay...</option>
                                </select>
                                @error('barangay')
                                    <small style="color:var(--danger);font-size:.78rem;display:block;margin-top:.3rem;">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Street / Purok / House No.</label>
                        <input type="text" name="street" class="cj-input"
                               value="{{ old('street', $user->street) }}">
                        @error('street')
                            <small style="color:var(--danger);font-size:.78rem;display:block;margin-top:.3rem;">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="mb-0">
                        <label class="cj-label">Landmark (Optional)</label>
                        <input type="text" name="landmark" class="cj-input"
                               value="{{ old('landmark', $user->landmark) }}">
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
                <a href="{{ route('customer.dashboard') }}" class="cj-back-link">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const phoneInput = document.getElementById('profile-phone');
    const phoneHint = document.getElementById('profile-phone-hint');
    if (phoneInput) {
        phoneInput.addEventListener('input', () => updatePhoneLiveHint(phoneInput, phoneHint));
        updatePhoneLiveHint(phoneInput, phoneHint);
    }

    initAddressCascade({
        provinceSelect: document.getElementById('profile-province'),
        municipalitySelect: document.getElementById('profile-municipality'),
        barangaySelect: document.getElementById('profile-barangay'),
        areas: @json($allAreas),
        oldProvince: @json(old('province', $user->province)),
        oldMunicipality: @json(old('municipality', $user->municipality)),
        oldBarangay: @json(old('barangay', $user->barangay)),
    });
})();
</script>
@endsection
