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
</style>
@endsection

@section('content')
<x-breadcrumbs :items="['Menu' => route('customer.menu'), 'Cart' => route('customer.cart'), 'Checkout' => null]" />
<h1 class="cj-page-title">Checkout</h1>

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
                @if($errors->any())
                    <div class="alert-cj alert-danger mb-3">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('customer.orders.store') }}" id="checkout-form">
                    @csrf
                    <div class="mb-3">
                        <label class="cj-label">Delivery Address <span style="color:var(--required);">*</span></label>
                        <textarea name="delivery_address" rows="3" required autocomplete="street-address"
                            class="cj-textarea">{{ old('delivery_address', auth()->user()->address) }}</textarea>
                        @error('delivery_address')
                            <small style="color:var(--danger);font-size:.78rem;display:block;margin-top:.3rem;">{{ $message }}</small>
                        @enderror
                    </div>
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

                    @php $selectedDeliveryType = old('delivery_type', 'asap'); @endphp
                    <div class="mb-3">
                        <label class="cj-label">Delivery Time <span style="color:var(--required);">*</span></label>
                        <div class="row g-2">
                            <div class="col-6">
                                <label for="dt-asap" class="delivery-type-card {{ $selectedDeliveryType === 'asap' ? 'active' : '' }}">
                                    <input type="radio" id="dt-asap" name="delivery_type" value="asap"
                                           class="visually-hidden delivery-type-input"
                                           {{ $selectedDeliveryType === 'asap' ? 'checked' : '' }}>
                                    <div style="font-size:1.3rem;">⚡</div>
                                    <div style="font-size:.85rem;font-weight:600;">As Soon As Possible</div>
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
                    </div>

                    <div id="schedule-panel" style="{{ $selectedDeliveryType === 'scheduled' ? '' : 'display:none;' }} margin-bottom:1rem;">
                        <div class="mb-3">
                            <label class="cj-label">Delivery Date</label>
                            <input type="date" name="delivery_date" id="delivery-date" class="cj-input"
                                   min="{{ $minDeliveryDate }}" max="{{ $maxDeliveryDate }}"
                                   value="{{ old('delivery_date', $minDeliveryDate) }}">
                        </div>
                        <div class="mb-1">
                            <label class="cj-label">Delivery Window</label>
                            <div id="slot-chips" style="display:flex;flex-wrap:wrap;gap:.5rem;"></div>
                            <input type="hidden" name="delivery_slot" id="delivery-slot-value" value="{{ old('delivery_slot') }}">
                            <p id="slot-empty-msg" style="display:none;color:var(--text-light);font-size:.85rem;margin-top:.5rem;">
                                No delivery windows available for this date — try another date.
                            </p>
                        </div>
                    </div>

                    @php $selectedPayment = old('payment_method', 'cash_on_delivery'); @endphp
                    <x-payment-method-selector
                        :selected-payment="$selectedPayment"
                        :total="$total + 50"
                        :gcash-qr="$gcashQr" />

                    <button type="submit" class="btn-cj btn-cj w-100"
                            style="width:100%;padding:.75rem;">
                        Place Order →
                    </button>
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
            .then(data => renderSlots(data.slots || []))
            .catch(() => {
                chipsContainer.innerHTML = '';
                emptyMsg.textContent = 'Could not load delivery times — please try again.';
                emptyMsg.style.display = 'block';
            });
    }

    function renderSlots(slots) {
        chipsContainer.innerHTML = '';

        if (slots.length === 0) {
            emptyMsg.textContent = 'No delivery windows available for this date — try another date.';
            emptyMsg.style.display = 'block';
            slotValueInput.value = '';
            return;
        }

        emptyMsg.style.display = 'none';
        const previouslySelected = slotValueInput.value;
        let stillValid = false;

        slots.forEach(slot => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'slot-chip';
            chip.textContent = slot.label;
            chip.setAttribute('aria-pressed', 'false');

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
})();
</script>
@endsection