@extends('layouts.customer')

@section('title', 'Checkout — Cojan Catering')

@section('styles')
<style>
    .delivery-type-card {
        display: block; cursor: pointer; border: 1.5px solid #ddd; border-radius: var(--radius-base);
        padding: 12px 14px; text-align: center; transition: all .2s ease;
    }
    .delivery-type-card.active { border-color: var(--green-dark); background: var(--green-light, #FBE8DE); }
    .delivery-type-card:focus-within { outline: 2px solid var(--green-dark); outline-offset: 2px; }

    /* 44px+ tap targets per the mobile brief — these are real buttons, not
       a dropdown, so the slot choice is thumb-friendly on a phone. */
    .slot-chip {
        min-height: 44px; padding: .5rem 1rem; border-radius: 100px;
        border: 1.5px solid #ddd; background: #fff; font-size: .85rem;
        cursor: pointer; transition: all .2s ease;
    }
    .slot-chip.active { background: var(--green-dark); color: #fff; border-color: var(--green-dark); }
    .slot-chip:focus-visible { outline: 2px solid var(--green-dark); outline-offset: 2px; }
    .slot-chip:disabled {
        background: #f1f1f1; color: #6b6b6b; border-color: #e0e0e0; border-style: dashed; cursor: not-allowed;
    }
    .slot-chip .slot-full-tag { text-decoration: none; display: inline-block; margin-left: .35rem; font-size: var(--fs-xs); font-weight: 600; }
    .delivery-type-card.disabled { opacity: .55; cursor: not-allowed; background: #f6f6f6; }

    .cj-confirm-duplicate {
        display: flex; align-items: flex-start; gap: .6rem; margin-top: .75rem;
        padding: .75rem; border: 1.5px solid var(--amber); border-radius: var(--radius-base);
        background: var(--amber-light); font-size: .85rem; cursor: pointer; min-height: 44px;
    }
    .cj-confirm-duplicate input { width: 20px; height: 20px; flex: 0 0 20px; margin-top: 1px; accent-color: var(--green-dark); }
</style>
@endsection

@section('content')
<x-breadcrumbs :items="['Menu' => route('customer.menu'), 'Cart' => route('customer.cart'), 'Checkout' => null]" />
<h1 class="cj-page-title">Checkout</h1>
@foreach($cartNotices as $notice)
    <div class="alert-cj alert-warning" role="status">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        <span>{{ $notice }} Your total below has been updated.</span>
    </div>
@endforeach
@if(\App\Services\BusinessHours::bypassActive())
    <div class="alert-cj alert-info" role="note" style="font-size:var(--fs-xs);">
        <i class="bi bi-wrench"></i> Local testing: business hours are bypassed (BUSINESS_HOURS_BYPASS). This never applies in production.
    </div>
@endif

<div class="row g-3 mt-1">
    <!-- Order Summary -->
    <div class="col-12 col-md-5">
        <div class="cj-card">
            <div class="cj-card-header">Order Summary</div>
            <div class="cj-card-body">
                @foreach($cart as $item)
                    <div class="d-flex justify-content-between mb-2" style="font-size:.9rem;">
                        <span>{{ $item['name'] }} x{{ $item['quantity'] }}</span>
                        <span>₱{{ number_format($item['price'] * $item['quantity'], 2) }}</span>
                    </div>
                @endforeach
                <hr>
                <div class="d-flex justify-content-between mb-1" style="font-size:.9rem;">
                    <span>Subtotal</span>
                    <span>₱{{ number_format($total, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-1" style="font-size:.9rem;">
                    <span>Delivery Fee</span>
                    <span>₱50.00</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between fw-bold">
                    <span>Total</span>
                    <span style="color:var(--green-dark);">
                        ₱{{ number_format($total + 50, 2) }}
                    </span>
                </div>
                <div class="mt-3">
                    <span id="payment-summary-pill" style="background:var(--green-dark);color:#fff;
                                 padding:4px 12px;border-radius:20px;font-size:.8rem;">
                        💵 Cash on Delivery
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Delivery Details -->
    <div class="col-12 col-md-7">
        <div class="cj-card">
            <div class="cj-card-header">Delivery Details</div>
            <div class="cj-card-body">
                @if($pendingLimitError && !$errors->has('pending_limit'))
                    <div class="alert-cj alert-warning mb-3" role="status" style="align-items:flex-start;">
                        <i class="bi bi-hourglass-split" style="margin-top:2px;"></i>
                        <div>
                            {{ $pendingLimitError }}
                            <a href="{{ route('customer.orders') }}" style="display:inline-block;font-weight:600;margin-top:.25rem;">View my orders →</a>
                        </div>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert-cj alert-danger mb-3">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('customer.orders.store') }}" id="checkout-form" data-submit-once>
                    @csrf
                    <input type="hidden" name="submission_token" value="{{ $submissionToken }}">
                    <div class="mb-3">
                        <label class="cj-label">Delivery Area <span style="color:var(--required);">*</span></label>
                        @if($hasMenuItems)
                            <input type="hidden" name="province" value="Occidental Mindoro">
                            <input type="hidden" name="municipality" value="San Jose">
                            <p style="font-size:.85rem;color:var(--text-light);margin:0 0 .5rem;">
                                Menu orders deliver only within San Jose, Occidental Mindoro — just pick your barangay.
                            </p>
                            @php $defaultBarangay = old('barangay', auth()->user()->municipality === 'San Jose' ? auth()->user()->barangay : null); @endphp
                            <select name="barangay" id="checkout-barangay" class="cj-select" required>
                                <option value="">Select barangay...</option>
                                @foreach($sanJoseBarangays as $barangayOption)
                                    <option value="{{ $barangayOption }}" {{ $defaultBarangay === $barangayOption ? 'selected' : '' }}>{{ $barangayOption }}</option>
                                @endforeach
                            </select>
                        @else
                            <div class="row g-2">
                                <div class="col-12 col-sm-4">
                                    <select name="province" id="checkout-province" class="cj-select" required>
                                        <option value="">Province...</option>
                                        @foreach(array_keys($cateringAreas) as $provinceOption)
                                            <option value="{{ $provinceOption }}" {{ old('province') === $provinceOption ? 'selected' : '' }}>{{ $provinceOption }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <select name="municipality" id="checkout-municipality" class="cj-select" required>
                                        <option value="">Municipality/City...</option>
                                    </select>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <select name="barangay" id="checkout-barangay" class="cj-select" required>
                                        <option value="">Barangay...</option>
                                    </select>
                                </div>
                            </div>
                        @endif
                        @error('barangay')
                            <small style="color:var(--danger);font-size:.78rem;display:block;margin-top:.3rem;">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Street / Purok / House No. <span style="color:var(--required);">*</span></label>
                        <input type="text" name="street" required autocomplete="street-address"
                               value="{{ old('street', auth()->user()->street) }}" class="cj-input">
                        @error('street')
                            <small style="color:var(--danger);font-size:.78rem;display:block;margin-top:.3rem;">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Landmark (Optional)</label>
                        <input type="text" name="landmark" placeholder="e.g. near the barangay hall"
                               value="{{ old('landmark', auth()->user()->landmark) }}" class="cj-input">
                    </div>
                    @if($pinMap)
                        <x-delivery-pin-map :config="$pinMap" />
                    @endif
                    <div class="mb-3">
                        <label class="cj-label">Contact Number <span style="color:var(--required);">*</span></label>
                        <input type="tel" name="delivery_phone" id="delivery-phone" required inputmode="numeric"
                               autocomplete="tel" maxlength="13"
                               value="{{ old('delivery_phone', auth()->user()->phone) }}" class="cj-input">
                        <small id="phone-hint" style="font-size:.78rem;color:var(--text-light);display:block;margin-top:.3rem;">
                            Format: 09XXXXXXXXX
                        </small>
                        @error('delivery_phone')
                            <small style="color:var(--danger);font-size:.78rem;display:block;margin-top:.3rem;">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Order Notes (Optional)</label>
                        <textarea name="notes" rows="2" placeholder="Special instructions..." class="cj-textarea"></textarea>
                    </div>

                    @php
                        // Today blocked by the admin → ASAP isn't possible at
                        // all (server rejects it too), so default to scheduling.
                        $selectedDeliveryType = $todayBlockedReason ? 'scheduled' : old('delivery_type', 'asap');
                    @endphp
                    <div class="mb-3">
                        <label class="cj-label">Delivery Time <span style="color:var(--required);">*</span></label>
                        <div class="row g-2">
                            <div class="col-6">
                                <label for="dt-asap" class="delivery-type-card {{ $selectedDeliveryType === 'asap' ? 'active' : '' }} {{ $todayBlockedReason ? 'disabled' : '' }}">
                                    <input type="radio" id="dt-asap" name="delivery_type" value="asap"
                                           class="visually-hidden delivery-type-input"
                                           {{ $selectedDeliveryType === 'asap' ? 'checked' : '' }}
                                           {{ $todayBlockedReason ? 'disabled' : '' }}>
                                    <div style="font-size:1.3rem;">⚡</div>
                                    <div style="font-size:.85rem;font-weight:600;">As Soon As Possible</div>
                                    @if($todayBlockedReason)
                                        <div style="font-size:var(--fs-xs);color:var(--danger);">Unavailable today</div>
                                    @endif
                                </label>
                            </div>
                            <div class="col-6">
                                <label for="dt-scheduled" class="delivery-type-card {{ $selectedDeliveryType === 'scheduled' ? 'active' : '' }}">
                                    <input type="radio" id="dt-scheduled" name="delivery_type" value="scheduled"
                                           class="visually-hidden delivery-type-input"
                                           {{ $selectedDeliveryType === 'scheduled' ? 'checked' : '' }}>
                                    <div style="font-size:1.3rem;">🗓️</div>
                                    <div style="font-size:.85rem;font-weight:600;">Schedule for Later</div>
                                </label>
                            </div>
                        </div>
                        @if($todayBlockedReason)
                            <p style="font-size:.8rem;color:var(--text-mid);margin:.5rem 0 0;">
                                <i class="bi bi-info-circle"></i>
                                We're not taking same-day orders today ({{ $todayBlockedReason }}). You can still schedule a delivery for another day.
                            </p>
                        @endif
                    </div>

                    <div id="schedule-panel" style="{{ $selectedDeliveryType === 'scheduled' ? '' : 'display:none;' }} margin-bottom:1rem;">
                        <div class="mb-3">
                            <label class="cj-label">Delivery Date</label>
                            <input type="date" name="delivery_date" id="delivery-date" class="cj-input"
                                   min="{{ $minDeliveryDate }}" max="{{ $maxDeliveryDate }}"
                                   value="{{ old('delivery_date', $minDeliveryDate) }}">
                            @if(!empty($blockedDeliveryDates))
                                <ul class="cj-unavailable-list">
                                    <li style="font-weight:600;">Closed dates:</li>
                                    @foreach($blockedDeliveryDates as $blockedDay => $blockedReason)
                                        <li>{{ \Illuminate\Support\Carbon::parse($blockedDay)->format('D, M d') }} — {{ $blockedReason }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                        <div class="mb-1">
                            <label class="cj-label">Delivery Window</label>
                            <div id="slot-chips" style="display:flex;flex-wrap:wrap;gap:.5rem;"></div>
                            <input type="hidden" name="delivery_slot" id="delivery-slot-value" value="{{ old('delivery_slot') }}">
                            <p id="slot-empty-msg" style="display:none;color:var(--text-light);font-size:.85rem;margin-top:.5rem;">
                                No delivery windows available for this date — try another date.
                            </p>
                        </div>
                        @if(session('needs_duplicate_slot_confirmation') || old('confirm_duplicate_slot'))
                            <label class="cj-confirm-duplicate" for="confirm-duplicate-slot">
                                <input type="checkbox" name="confirm_duplicate_slot" id="confirm-duplicate-slot" value="1"
                                       {{ old('confirm_duplicate_slot') ? 'checked' : '' }}>
                                <span>Yes, I want a <strong>second order</strong> in this same delivery time frame.</span>
                            </label>
                        @endif
                    </div>

                    @php $selectedPayment = old('payment_method', 'cash_on_delivery'); @endphp
                    <x-payment-method-selector
                        :selected-payment="$selectedPayment"
                        :total="$total + 50"
                        :gcash-qr="$gcashQr" />

                    @if($pendingLimitError)
                        <button type="submit" class="btn-cj btn-cj w-100" disabled data-keep-disabled
                                style="width:100%;padding:.75rem;">
                            Order limit reached
                        </button>
                    @else
                        <button type="submit" class="btn-cj btn-cj w-100" data-loading-text="Placing order…"
                                style="width:100%;padding:.75rem;">
                            Place Order →
                        </button>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const typeInputs = document.querySelectorAll('.delivery-type-input');
    const typeCards = document.querySelectorAll('.delivery-type-card');
    const schedulePanel = document.getElementById('schedule-panel');
    const dateInput = document.getElementById('delivery-date');
    const chipsContainer = document.getElementById('slot-chips');
    const slotValueInput = document.getElementById('delivery-slot-value');
    const emptyMsg = document.getElementById('slot-empty-msg');

    function updateTypeUI() {
        const selected = document.querySelector('.delivery-type-input:checked');
        const isScheduled = !!selected && selected.value === 'scheduled';

        typeCards.forEach(card => {
            card.classList.toggle('active', card.querySelector('.delivery-type-input').checked);
        });
        schedulePanel.style.display = isScheduled ? '' : 'none';

        if (isScheduled) loadSlots();
    }

    function loadSlots() {
        chipsContainer.innerHTML = '<span style="color:var(--text-light);font-size:.85rem;">Loading available times...</span>';
        emptyMsg.style.display = 'none';

        fetch(`{{ route('customer.checkout.delivery-slots') }}?date=${encodeURIComponent(dateInput.value)}`)
            .then(r => r.json())
            .then(data => renderSlots(data.slots || [], data.blocked || null))
            .catch(() => {
                chipsContainer.innerHTML = '';
                emptyMsg.textContent = 'Could not load delivery times — please try again.';
                emptyMsg.style.display = 'block';
            });
    }

    function renderSlots(slots, blockedReason) {
        chipsContainer.innerHTML = '';

        if (slots.length === 0) {
            emptyMsg.textContent = blockedReason
                ? `We're closed on this date (${blockedReason}) — please choose another date.`
                : 'No delivery windows available for this date — try another date.';
            emptyMsg.style.display = 'block';
            slotValueInput.value = '';
            return;
        }

        if (slots.every(slot => slot.full)) {
            emptyMsg.textContent = 'Every delivery window on this date is fully booked — please choose another date.';
            emptyMsg.style.display = 'block';
        } else {
            emptyMsg.style.display = 'none';
        }
        const previouslySelected = slotValueInput.value;
        let stillValid = false;

        slots.forEach(slot => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'slot-chip';
            chip.textContent = slot.label;
            chip.setAttribute('aria-pressed', 'false');

            // Full time frames stay visible (so the customer can see why a
            // time is missing) but can't be picked; the server re-checks
            // capacity under a lock regardless.
            if (slot.full) {
                chip.disabled = true;
                const tag = document.createElement('span');
                tag.className = 'slot-full-tag';
                tag.textContent = 'Full';
                chip.appendChild(tag);
                chip.setAttribute('aria-label', `${slot.label}, fully booked`);
                chipsContainer.appendChild(chip);
                return;
            }

            if (slot.value === previouslySelected) {
                chip.classList.add('active');
                chip.setAttribute('aria-pressed', 'true');
                stillValid = true;
            }

            chip.addEventListener('click', () => {
                chipsContainer.querySelectorAll('.slot-chip').forEach(c => {
                    c.classList.remove('active');
                    c.setAttribute('aria-pressed', 'false');
                });
                chip.classList.add('active');
                chip.setAttribute('aria-pressed', 'true');
                slotValueInput.value = slot.value;
            });

            chipsContainer.appendChild(chip);
        });

        if (!stillValid) slotValueInput.value = '';
    }

    typeInputs.forEach(input => input.addEventListener('change', updateTypeUI));
    dateInput.addEventListener('change', loadSlots);

    document.getElementById('checkout-form').addEventListener('submit', (e) => {
        const selected = document.querySelector('.delivery-type-input:checked');
        if (selected && selected.value === 'scheduled' && !slotValueInput.value) {
            e.preventDefault();
            emptyMsg.textContent = 'Please choose a delivery window before placing your order.';
            emptyMsg.style.display = 'block';
        }
    });

    updateTypeUI();
    @if(old('delivery_type') === 'scheduled')
        loadSlots();
    @endif

    // Live format hint — mirrors the server-side PhilippineMobileNumber
    // rule exactly, but never blocks typing or submission on its own.
    const phoneInput = document.getElementById('delivery-phone');
    const phoneHint = document.getElementById('phone-hint');
    phoneInput.addEventListener('input', () => updatePhoneLiveHint(phoneInput, phoneHint));
    updatePhoneLiveHint(phoneInput, phoneHint);

    @if(!$hasMenuItems)
        initAddressCascade({
            provinceSelect: document.getElementById('checkout-province'),
            municipalitySelect: document.getElementById('checkout-municipality'),
            barangaySelect: document.getElementById('checkout-barangay'),
            areas: @json($cateringAreas),
            oldProvince: @json(old('province', auth()->user()->province)),
            oldMunicipality: @json(old('municipality', auth()->user()->municipality)),
            oldBarangay: @json(old('barangay', auth()->user()->barangay)),
        });
    @endif
})();
</script>
@endsection