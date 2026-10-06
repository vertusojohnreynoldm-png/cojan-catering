{{--
    Shared by customer/checkout.blade.php (cart flow) and
    customer/package-checkout.blade.php (package booking flow) — extracted so
    the COD/GCash cards, GCash QR reveal, and reference-number field aren't
    duplicated across two checkout contexts.

    Props:
      selectedPayment (string) — 'cash_on_delivery' | 'gcash'
      total (float)            — amount shown in the GCash payment instructions
      gcashQr (string|null)    — pre-generated QR SVG, or null on generation failure
--}}
@props(['selectedPayment', 'total', 'gcashQr'])

<style>
    .payment-option-card.active {
        border-color: var(--green-dark) !important;
        background: var(--green-light, #FBE8DE);
    }
    /* Keyboard fix: the radio inputs are visually hidden via .visually-hidden
       (Bootstrap's focusable sr-only utility) rather than d-none/display:none,
       which removed them from the tab order entirely. :focus-within on the
       label surfaces a visible focus ring since the native input has none. */
    .payment-option-card:focus-within {
        outline: 2px solid var(--green-dark);
        outline-offset: 2px;
    }
</style>

<div class="mb-3">
    <label style="font-weight:600;font-size:.9rem;margin-bottom:.4rem;display:block;">
        Payment Method <span style="color:#e74c3c;">*</span>
    </label>
    <p style="font-size:.78rem;color:var(--text-light);margin-bottom:.6rem;display:flex;align-items:center;gap:.35rem;">
        <i class="bi bi-shield-check"></i> Secure checkout — your information is only used to fulfill this order.
    </p>
    <div class="row g-2">
        <div class="col-6">
            <label for="pay-cod" class="payment-option-card {{ $selectedPayment === 'cash_on_delivery' ? 'active' : '' }}"
                   style="display:block;cursor:pointer;border:1.5px solid #ddd;border-radius:10px;
                          padding:12px 14px;text-align:center;transition:all .2s ease;">
                <input type="radio" id="pay-cod" name="payment_method" value="cash_on_delivery"
                       class="visually-hidden payment-option-input"
                       {{ $selectedPayment === 'cash_on_delivery' ? 'checked' : '' }}>
                <div style="font-size:1.3rem;">💵</div>
                <div style="font-size:.85rem;font-weight:600;">Cash on Delivery</div>
            </label>
        </div>
        <div class="col-6">
            <label for="pay-gcash" class="payment-option-card {{ $selectedPayment === 'gcash' ? 'active' : '' }}"
                   style="display:block;cursor:pointer;border:1.5px solid #ddd;border-radius:10px;
                          padding:12px 14px;text-align:center;transition:all .2s ease;">
                <input type="radio" id="pay-gcash" name="payment_method" value="gcash"
                       class="visually-hidden payment-option-input"
                       {{ $selectedPayment === 'gcash' ? 'checked' : '' }}>
                <div style="font-size:1.3rem;">📱</div>
                <div style="font-size:.85rem;font-weight:600;">GCash</div>
            </label>
        </div>
    </div>
</div>

<div id="gcash-panel" style="{{ $selectedPayment === 'gcash' ? '' : 'display:none;' }}
            background:#f4f9f6;border-radius:10px;padding:14px;margin-bottom:1rem;text-align:center;">
    @if($gcashQr)
        <div id="gcash-qr-wrap" style="position:relative;display:inline-block;max-width:200px;width:100%;margin-bottom:.75rem;">
            <div id="gcash-qr-real" role="img" aria-label="QR code for GCash payment"
                 style="padding:10px;background:#fff;border-radius:8px;
                        box-shadow:0 2px 10px rgba(0,0,0,.1);transition:opacity .35s ease,transform .35s ease;">
                <div aria-hidden="true">{!! $gcashQr !!}</div>
            </div>
            <div id="gcash-qr-placeholder" style="display:none;position:absolute;inset:0;background:#fff;
                        border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.1);flex-direction:column;
                        align-items:center;justify-content:center;gap:.5rem;transition:opacity .35s ease;">
                <span class="spinner-border" style="width:1.6rem;height:1.6rem;color:var(--green-dark);" role="status"></span>
                <span style="font-size:.72rem;color:var(--text-light);">Generating secure QR code…</span>
            </div>
        </div>
    @else
        <p style="color:var(--text-light);font-size:.85rem;margin-bottom:.75rem;">
            QR code temporarily unavailable — you can still send payment manually to
            <strong>{{ config('services.gcash.number') ?: 'our GCash number' }}</strong>.
        </p>
    @endif
    <p style="font-size:.85rem;color:var(--text-mid);margin-bottom:.75rem;">
        Scan this QR in your GCash app to send payment, then enter the exact amount
        (₱{{ number_format($total, 2) }}) and your GCash reference number below.
    </p>
    <p style="font-size:.78rem;color:var(--text-light);margin-bottom:.75rem;display:flex;align-items:center;gap:.35rem;justify-content:center;">
        <i class="bi bi-clock-history"></i> Payment verified manually by our team within a few hours — not an instant automated gateway.
    </p>
    <label style="font-weight:600;font-size:.9rem;margin-bottom:.4rem;display:block;text-align:left;">
        GCash Reference Number <span style="color:#e74c3c;">*</span>
    </label>
    <input type="text" name="gcash_reference" id="gcash_reference"
           value="{{ old('gcash_reference') }}"
           placeholder="e.g. 1234567890123"
           {{ $selectedPayment === 'gcash' ? 'required' : '' }}
           style="width:100%;border:1.5px solid #ddd;border-radius:10px;
                  padding:10px 14px;font-size:.9rem;outline:none;">
</div>

<script>
(function () {
    const inputs = document.querySelectorAll('.payment-option-input');
    const cards = document.querySelectorAll('.payment-option-card');
    const gcashPanel = document.getElementById('gcash-panel');
    const gcashReference = document.getElementById('gcash_reference');
    const summaryPill = document.getElementById('payment-summary-pill');
    const gcashQrWrap = document.getElementById('gcash-qr-wrap');
    const gcashQrReal = document.getElementById('gcash-qr-real');
    const gcashQrPlaceholder = document.getElementById('gcash-qr-placeholder');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let gcashQrTimer = null;

    function playGcashQrReveal() {
        if (!gcashQrWrap || !gcashQrReal || !gcashQrPlaceholder || prefersReducedMotion) return;

        clearTimeout(gcashQrTimer);
        gcashQrPlaceholder.style.display = 'flex';
        gcashQrPlaceholder.style.opacity = '1';
        gcashQrReal.style.opacity = '0';
        gcashQrReal.style.transform = 'scale(.94)';

        gcashQrTimer = setTimeout(() => {
            gcashQrPlaceholder.style.opacity = '0';
            gcashQrReal.style.opacity = '1';
            gcashQrReal.style.transform = 'scale(1)';
            setTimeout(() => { gcashQrPlaceholder.style.display = 'none'; }, 350);
        }, 900);
    }

    function updatePaymentUI(animate) {
        const selected = document.querySelector('.payment-option-input:checked');
        const isGcash = selected && selected.value === 'gcash';

        cards.forEach(card => {
            const input = card.querySelector('.payment-option-input');
            card.classList.toggle('active', input.checked);
        });

        gcashPanel.style.display = isGcash ? '' : 'none';
        if (gcashReference) gcashReference.required = isGcash;

        if (summaryPill) {
            summaryPill.textContent = isGcash ? '📱 GCash' : '💵 Cash on Delivery';
        }

        if (isGcash && animate) {
            playGcashQrReveal();
        }
    }

    inputs.forEach(input => input.addEventListener('change', () => updatePaymentUI(true)));
    updatePaymentUI(false);
})();
</script>
