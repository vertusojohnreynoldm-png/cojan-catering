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
                            border-radius:var(--radius-md);padding:.4rem .65rem;text-align:center;box-shadow:var(--shadow-md);
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
            <form method="POST" action="{{ route('customer.packages.avail') }}" id="avail-form">
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
                        <label class="cj-label">Your Name <span style="color:var(--required);">*</span></label>
                        <input type="text" name="contact_name" class="cj-input" required
                               value="{{ old('contact_name', auth()->user()->name) }}">
                        @error('contact_name')
                            <small style="color:var(--danger);font-size:.78rem;display:block;margin-top:.3rem;">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Contact Number <span style="color:var(--required);">*</span></label>
                        <input type="tel" name="contact_number" id="avail-contact-number" class="cj-input" required
                               inputmode="numeric" maxlength="13"
                               value="{{ old('contact_number', auth()->user()->phone) }}">
                        <small id="avail-phone-hint" style="font-size:.78rem;color:var(--text-light);display:block;margin-top:.3rem;">
                            Format: 09XXXXXXXXX
                        </small>
                        @error('contact_number')
                            <small style="color:var(--danger);font-size:.78rem;display:block;margin-top:.3rem;">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Event Area <span style="color:var(--required);">*</span></label>
                        <p style="font-size:.78rem;color:var(--text-light);margin:0 0 .5rem;">
                            We cater anywhere in Occidental Mindoro or Oriental Mindoro.
                        </p>
                        <div class="row g-2">
                            <div class="col-12 col-sm-4">
                                <select name="province" id="avail-province" class="cj-select" required>
                                    <option value="">Province...</option>
                                    @foreach(array_keys($cateringAreas) as $provinceOption)
                                        <option value="{{ $provinceOption }}" {{ old('province') === $provinceOption ? 'selected' : '' }}>{{ $provinceOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-sm-4">
                                <select name="municipality" id="avail-municipality" class="cj-select" required>
                                    <option value="">Municipality/City...</option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-4">
                                <select name="barangay" id="avail-barangay" class="cj-select" required>
                                    <option value="">Barangay...</option>
                                </select>
                            </div>
                        </div>
                        @error('barangay')
                            <small style="color:var(--danger);font-size:.78rem;display:block;margin-top:.3rem;">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Street / Purok / Venue Name <span style="color:var(--required);">*</span></label>
                        <input type="text" name="street" class="cj-input" required
                               value="{{ old('street') }}">
                        @error('street')
                            <small style="color:var(--danger);font-size:.78rem;display:block;margin-top:.3rem;">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Landmark (Optional)</label>
                        <input type="text" name="landmark" class="cj-input" placeholder="e.g. beside the covered court"
                               value="{{ old('landmark') }}">
                    </div>
                    @php
                        $oldEventDate = old('event_date') ? \Illuminate\Support\Carbon::parse(old('event_date')) : null;
                        $oldEventDay = old('event_day', $oldEventDate?->toDateString());
                        $oldEventTime = old('event_time', $oldEventDate?->format('H:i'));
                        $eventTimes = [];
                        for ($t = \Illuminate\Support\Carbon::createFromTime(6, 0); $t->lte(\Illuminate\Support\Carbon::createFromTime(22, 0)); $t->addMinutes(30)) {
                            $eventTimes[$t->format('H:i')] = $t->format('g:i A');
                        }
                    @endphp
                    <div class="mb-3">
                        <label class="cj-label" for="avail-event-day">Event Date <span style="color:var(--required);">*</span></label>
                        <p style="font-size:.78rem;color:var(--text-light);margin:0 0 .5rem;">
                            Book at least {{ $eventCalendar['leadDays'] }} {{ str('day')->plural($eventCalendar['leadDays']) }} ahead —
                            available from <strong>{{ \Illuminate\Support\Carbon::parse($eventCalendar['minDate'])->format('M d, Y') }}</strong>
                            to <strong>{{ \Illuminate\Support\Carbon::parse($eventCalendar['maxDate'])->format('M d, Y') }}</strong>.
                        </p>
                        <div class="cj-event-calendar">
                            <input type="date" name="event_day" id="avail-event-day" class="cj-input"
                                   min="{{ $eventCalendar['minDate'] }}" max="{{ $eventCalendar['maxDate'] }}"
                                   value="{{ $oldEventDay }}">
                        </div>
                        <p id="avail-event-day-readout" style="display:none;font-size:.85rem;margin:.5rem 0 0;" aria-live="polite"></p>
                        <div class="cj-calendar-legend" id="avail-calendar-legend" style="display:none;">
                            <span class="cj-legend-selected">Your date</span>
                            <span class="cj-legend-full">Fully booked</span>
                            <span class="cj-legend-blocked">Closed</span>
                        </div>
                        <ul class="cj-unavailable-list" id="avail-unavailable-list" aria-label="Unavailable dates"></ul>
                        @error('event_date')
                            <small style="color:var(--danger);font-size:.78rem;display:block;margin-top:.3rem;">{{ $message }}</small>
                        @enderror
                        <small id="avail-event-day-error" style="color:var(--danger);font-size:.78rem;display:none;margin-top:.3rem;"></small>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label" for="avail-event-time">Event Start Time <span style="color:var(--required);">*</span></label>
                        <select name="event_time" id="avail-event-time" class="cj-select" required>
                            <option value="">Select time...</option>
                            @foreach($eventTimes as $value => $label)
                                <option value="{{ $value }}" {{ $oldEventTime === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
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

@section('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script>
(function () {
    const modal = document.getElementById('availModal');

    // ---- Event date calendar --------------------------------------------
    // Full and admin-blocked dates come from BookingCapacity and are greyed
    // out here, but this is only a hint: storeBooking() and store() re-check
    // everything server-side (store() under a row lock). If the flatpickr
    // CDN fails to load, the plain <input type="date"> (with min/max) stays
    // as a fallback and the unavailable dates are listed as text instead.
    const calendar = @json($eventCalendar);
    const dayInput = document.getElementById('avail-event-day');
    const readout = document.getElementById('avail-event-day-readout');
    const legend = document.getElementById('avail-calendar-legend');
    const unavailableList = document.getElementById('avail-unavailable-list');
    const dayError = document.getElementById('avail-event-day-error');

    function formatDay(ymd) {
        const [y, m, d] = ymd.split('-').map(Number);
        return new Date(y, m - 1, d).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    }

    function renderUnavailable(year, month) {
        // month is 0-based; null year = list everything (fallback mode).
        const prefix = year === null ? '' : `${year}-${String(month + 1).padStart(2, '0')}-`;
        const entries = Object.entries(calendar.unavailable).filter(([d]) => d.startsWith(prefix));
        unavailableList.innerHTML = '';
        if (entries.length === 0) return;

        const heading = document.createElement('li');
        heading.style.fontWeight = '600';
        heading.textContent = year === null ? 'Unavailable dates:' : 'Unavailable this month:';
        unavailableList.appendChild(heading);

        entries.forEach(([d, reason]) => {
            const li = document.createElement('li');
            li.textContent = `${formatDay(d)} — ${reason}`;
            unavailableList.appendChild(li);
        });
    }

    function showReadout(ymd) {
        readout.style.display = ymd ? 'block' : 'none';
        readout.innerHTML = '';
        if (!ymd) return;
        const strong = document.createElement('strong');
        strong.textContent = formatDay(ymd);
        readout.append('Selected: ', strong);
        dayError.style.display = 'none';
    }

    if (window.flatpickr && dayInput) {
        dayInput.type = 'text';
        const fp = flatpickr(dayInput, {
            inline: true,
            dateFormat: 'Y-m-d',
            minDate: calendar.minDate,
            maxDate: calendar.maxDate,
            disable: Object.keys(calendar.unavailable),
            defaultDate: dayInput.value || null,
            disableMobile: true,
            monthSelectorType: 'static',
            onDayCreate(_, __, ___, dayElem) {
                const d = dayElem.dateObj;
                const ymd = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                const reason = calendar.unavailable[ymd];
                if (reason) {
                    dayElem.classList.add(reason === 'Fully booked' ? 'cj-day-full' : 'cj-day-blocked');
                    dayElem.title = reason;
                    dayElem.setAttribute('aria-label', `${formatDay(ymd)}, unavailable: ${reason}`);
                }
            },
            onChange(_, ymd) { showReadout(ymd); },
            onMonthChange(_, __, instance) { renderUnavailable(instance.currentYear, instance.currentMonth); },
            onYearChange(_, __, instance) { renderUnavailable(instance.currentYear, instance.currentMonth); },
        });
        dayInput.style.display = 'none';
        legend.style.display = 'flex';
        renderUnavailable(fp.currentYear, fp.currentMonth);
        showReadout(dayInput.value);
    } else if (dayInput) {
        renderUnavailable(null, null);
    }

    document.getElementById('avail-form').addEventListener('submit', (e) => {
        if (!dayInput.value) {
            e.preventDefault();
            dayError.textContent = 'Please pick an event date on the calendar.';
            dayError.style.display = 'block';
            dayError.scrollIntoView({ block: 'center', behavior: 'smooth' });
            return;
        }
        // Mirrors the server check so a picked-then-filled date gets a
        // message instantly; the server still has the final say.
        if (calendar.unavailable[dayInput.value]) {
            e.preventDefault();
            dayError.textContent = `${formatDay(dayInput.value)} is unavailable (${calendar.unavailable[dayInput.value]}). Please choose another date.`;
            dayError.style.display = 'block';
        }
    });

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

    const phoneInput = document.getElementById('avail-contact-number');
    const phoneHint = document.getElementById('avail-phone-hint');
    if (phoneInput) {
        phoneInput.addEventListener('input', () => updatePhoneLiveHint(phoneInput, phoneHint));
        updatePhoneLiveHint(phoneInput, phoneHint);
    }

    initAddressCascade({
        provinceSelect: document.getElementById('avail-province'),
        municipalitySelect: document.getElementById('avail-municipality'),
        barangaySelect: document.getElementById('avail-barangay'),
        areas: @json($cateringAreas),
        oldProvince: @json(old('province')),
        oldMunicipality: @json(old('municipality')),
        oldBarangay: @json(old('barangay')),
    });
})();
</script>
@endsection
