@extends('layouts.customer')

@section('title', 'Catering Packages — Cojan Catering')

@section('content')
@php
    // If we just bounced back here from a failed Avail submission, look up
    // which package the modal was open for so it can reopen pre-filled
    // instead of the customer landing on a blank page with errors and no
    // visible form.
    $erroredPackage = $errors->any() && old('package_id')
        ? $packages->firstWhere('id', (int) old('package_id'))
        : null;
@endphp
<x-breadcrumbs :items="['Catering Packages' => null]" />
<h1 class="cj-page-title">Catering Packages</h1>
<p class="cj-page-sub">Pax-based packages for events — dishes, chafing dishes, and utensils all included</p>

<div class="row g-3">
    @forelse($packages as $package)
    <div class="col-12 col-lg-6">
        <div class="menu-card">
            <div class="menu-card-img">
                <span style="position:absolute;top:10px;left:10px;z-index:2;background:var(--amber);color:var(--text-dark);
                             font-size:.68rem;font-weight:700;padding:.25rem .6rem;border-radius:100px;
                             box-shadow:var(--shadow-sm);display:inline-flex;align-items:center;gap:.3rem;">
                    📦 Package Deal
                </span>
                <div style="position:absolute;top:10px;right:10px;z-index:2;background:var(--green-dark);color:#fff;
                            border-radius:12px;padding:.4rem .65rem;text-align:center;box-shadow:var(--shadow-md);
                            line-height:1;">
                    <div style="font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:700;">{{ $package->pax }}</div>
                    <div style="font-size:.6rem;text-transform:uppercase;letter-spacing:.05em;opacity:.85;">pax</div>
                </div>
                @if($package->image)
                    <img src="{{ asset('storage/' . $package->image) }}" alt="{{ $package->name }}">
                @else
                    <span>🍽 No Image</span>
                @endif
            </div>
            <div class="menu-card-body">
                <div class="menu-card-name">{{ $package->name }}</div>
                <div class="menu-card-desc">{{ $package->description }}</div>

                @if($package->menuItems->isNotEmpty())
                    <div style="margin-bottom:.6rem;">
                        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;
                                    color:var(--green-dark);margin-bottom:.35rem;display:flex;align-items:center;gap:.35rem;">
                            <i class="bi bi-check2-circle"></i> What's Included
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($package->menuItems as $item)
                                <span style="background:var(--green-light);color:var(--green-dark);font-size:.75rem;
                                             padding:.2rem .55rem;border-radius:100px;">
                                    {{ $item->pivot->quantity }}x {{ $item->name }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($package->utensils->isNotEmpty())
                    <div style="margin-bottom:.75rem;">
                        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;
                                    color:var(--text-mid);margin-bottom:.35rem;display:flex;align-items:center;gap:.35rem;">
                            <i class="bi bi-tools"></i> Equipment Provided
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($package->utensils as $utensil)
                                <span style="background:var(--cream);color:var(--text-mid);font-size:.75rem;
                                             padding:.2rem .55rem;border-radius:100px;border:1px solid rgba(122,46,29,.12);">
                                    {{ $utensil->pivot->quantity_needed }} {{ $utensil->name }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="menu-card-price">₱{{ number_format($package->price, 2) }}</div>
                <button type="button" class="btn-cj-amber btn-cj" style="width:100%;justify-content:center;padding:.55rem;"
                        data-bs-toggle="modal" data-bs-target="#availModal"
                        data-package-id="{{ $package->id }}"
                        data-package-name="{{ $package->name }}"
                        data-package-pax="{{ $package->pax }}"
                        data-package-price="{{ number_format($package->price, 2) }}">
                    Avail This Package
                </button>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="alert-cj alert-info">No catering packages available right now.</div>
    </div>
    @endforelse
</div>

<!-- ========== AVAIL MODAL (shared, populated per package via data-* attrs) ========== -->
<div class="modal fade" id="availModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="avail-modal-title">{{ $erroredPackage->name ?? 'Book Package' }}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('customer.packages.avail') }}">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="package_id" id="avail-package-id"
                           value="{{ old('package_id', $erroredPackage->id ?? '') }}">
                    <p id="avail-modal-summary" style="font-size:.85rem;color:var(--text-mid);margin-bottom:1rem;">
                        @if($erroredPackage)
                            {{ $erroredPackage->name }} — {{ $erroredPackage->pax }} pax — ₱{{ number_format($erroredPackage->price, 2) }}
                        @endif
                    </p>

                    @if($errors->any())
                        <div class="alert-cj alert-danger mb-3">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="cj-label">Your Name <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="contact_name" class="cj-input" required
                               value="{{ old('contact_name', auth()->user()->name) }}">
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Contact Number <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="contact_number" class="cj-input" required
                               value="{{ old('contact_number', auth()->user()->phone) }}">
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Event Venue / Address <span style="color:#e74c3c;">*</span></label>
                        <textarea name="venue" class="cj-textarea" rows="2" required>{{ old('venue') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Event Date &amp; Time <span style="color:#e74c3c;">*</span></label>
                        <input type="datetime-local" name="event_date" id="avail-event-date" class="cj-input" required
                               value="{{ old('event_date') }}">
                        <small style="color:var(--text-light);font-size:.78rem;">
                            Must be at least 24 hours from now, to allow prep time.
                        </small>
                    </div>
                    <div class="mb-1">
                        <label class="cj-label">Event Type (Optional)</label>
                        <select name="event_type" class="cj-select">
                            <option value="">Select type...</option>
                            <option value="Birthday" {{ old('event_type') === 'Birthday' ? 'selected' : '' }}>Birthday</option>
                            <option value="Wedding" {{ old('event_type') === 'Wedding' ? 'selected' : '' }}>Wedding</option>
                            <option value="Corporate Event" {{ old('event_type') === 'Corporate Event' ? 'selected' : '' }}>Corporate Event</option>
                            <option value="Other" {{ old('event_type') === 'Other' ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cj"
                            style="background:transparent;color:var(--green-dark);border-color:var(--green-dark);"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-cj-amber btn-cj">Continue to Payment →</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const modal = document.getElementById('availModal');
    const eventDateInput = document.getElementById('avail-event-date');

    // Client-side hint matching the server's 24-hour minimum lead time —
    // the server-side validation is what's authoritative.
    function minEventDateTimeLocal() {
        const d = new Date(Date.now() + 24 * 60 * 60 * 1000);
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        return d.toISOString().slice(0, 16);
    }
    if (eventDateInput) {
        eventDateInput.min = minEventDateTimeLocal();
    }

    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button || !button.hasAttribute('data-package-id')) return;

        document.getElementById('avail-package-id').value = button.getAttribute('data-package-id');
        document.getElementById('avail-modal-title').textContent = button.getAttribute('data-package-name');
        document.getElementById('avail-modal-summary').textContent =
            button.getAttribute('data-package-name') + ' — '
            + button.getAttribute('data-package-pax') + ' pax — ₱'
            + button.getAttribute('data-package-price');
    });

    @if($errors->any())
        new bootstrap.Modal(modal).show();
    @endif
})();
</script>
@endsection
