{{--
    Customer booking detail: the catering downpayment — what's owed, by
    when, the GCash QR, and the receipt upload (resized to ≤1280px JPEG in
    the browser first, same as the rider's proof-of-delivery photo).
    Renders nothing for orders without a downpayment.

    Props: order (Order), gcashQr (string|null)
--}}
@props(['order', 'gcashQr' => null])

@if($order->requiresDownpayment())
@php
    $expired = $order->status === 'cancelled' && $order->cancellation_reason === \App\Services\UnpaidBookingExpirer::REASON;
@endphp
<div class="cj-card mb-3 cj-pay-card" id="downpayment">
    <div class="cj-card-header">Downpayment</div>
    <div class="cj-card-body">
        {{-- State message first: it's what the customer needs to act on. --}}
        @if($expired)
            <div class="alert-cj alert-danger" role="status">
                <i class="bi bi-x-circle"></i>
                <span>This booking was cancelled because the downpayment wasn't received by {{ $order->payment_due_at->format('M d, Y g:i A') }}. You're welcome to book again.</span>
            </div>
        @elseif($order->downpayment_paid_at)
            <div class="alert-cj alert-success" role="status">
                <i class="bi bi-check-circle"></i>
                <span>Downpayment confirmed — your date is secured. The balance is paid on the event day (cash or GCash).</span>
            </div>
        @elseif($order->hasReceiptUnderReview())
            <div class="alert-cj alert-info" role="status">
                <i class="bi bi-hourglass-split"></i>
                <span>Receipt received {{ $order->receipt_uploaded_at->format('M d, g:i A') }} — we're verifying your payment. Your booking won't expire while we check.</span>
            </div>
        @elseif($order->receipt_rejected_at && $order->canUploadReceipt())
            <div class="alert-cj alert-danger" role="alert" style="align-items:flex-start;">
                <i class="bi bi-exclamation-triangle" style="margin-top:2px;"></i>
                <span>
                    We couldn't verify your receipt: <strong>{{ $order->receipt_rejection_reason }}</strong><br>
                    Please upload a clear screenshot by <strong>{{ $order->payment_due_at->format('M d, g:i A') }}</strong>.
                </span>
            </div>
        @elseif($order->canUploadReceipt())
            <div class="alert-cj alert-warning" role="status" style="align-items:flex-start;">
                <i class="bi bi-alarm" style="margin-top:2px;"></i>
                <span>
                    Pay the downpayment and upload your receipt by
                    <strong>{{ $order->payment_due_at->format('D, M d, Y g:i A') }}</strong>
                    ({{ $order->payment_due_at->diffForHumans(['parts' => 2]) }}) to secure your date.
                </span>
            </div>
        @endif

        <x-downpayment-breakdown :total="$order->total_amount" :downpayment="$order->downpayment_amount"
                                 :balance="$order->balanceAmount()"
                                 :deadline="$order->isAwaitingDownpayment() ? $order->payment_due_at : null"
                                 :paid-at="$order->downpayment_paid_at" />

        @if($order->canUploadReceipt())
            <div class="row g-3 mt-1 align-items-start">
                <div class="col-12 col-md-5 text-center">
                    <p class="cj-label mb-2">1. Pay ₱{{ number_format($order->downpayment_amount, 2) }} via GCash</p>
                    @if($gcashQr)
                        <div class="cj-pay-qr" role="img" aria-label="GCash QR code for the downpayment">
                            <div aria-hidden="true">{!! $gcashQr !!}</div>
                        </div>
                    @endif
                    <p style="font-size:var(--fs-xs);color:var(--text-light);margin:.5rem 0 0;">
                        @if(config('services.gcash.number'))
                            GCash number: <strong>{{ config('services.gcash.number') }}</strong><br>
                        @endif
                        Enter the exact amount: <strong>₱{{ number_format($order->downpayment_amount, 2) }}</strong>
                    </p>
                </div>
                <div class="col-12 col-md-7">
                    <form method="POST" action="{{ route('customer.orders.receipt.store', $order->id) }}"
                          enctype="multipart/form-data" id="receipt-form" novalidate>
                        @csrf
                        <p class="cj-label mb-2">2. Upload your GCash receipt</p>
                        @if($order->hasReceiptUnderReview())
                            <p style="font-size:var(--fs-xs);color:var(--text-light);">Uploaded the wrong picture? You can replace it until we've checked it.</p>
                        @endif
                        <label for="receipt-input" class="cj-upload-drop" id="receipt-drop">
                            <i class="bi bi-image" aria-hidden="true"></i>
                            <span id="receipt-drop-text">Tap to choose a screenshot or photo</span>
                            <small>JPG, PNG or WEBP</small>
                        </label>
                        <input type="file" name="receipt" id="receipt-input" accept="image/jpeg,image/png,image/webp" class="visually-hidden">
                        <div id="receipt-preview" style="display:none;" class="cj-receipt-preview">
                            <img id="receipt-preview-img" alt="Selected receipt preview">
                        </div>
                        <label for="receipt-ref" class="cj-label mt-3">GCash reference number (optional)</label>
                        <input type="text" name="gcash_reference" id="receipt-ref" class="cj-input" inputmode="numeric"
                               maxlength="30" placeholder="e.g. 1234 567 890123" value="{{ old('gcash_reference', $order->gcash_reference) }}">
                        @error('receipt')
                            <small class="cj-field-error">{{ $message }}</small>
                        @enderror
                        @error('gcash_reference')
                            <small class="cj-field-error">{{ $message }}</small>
                        @enderror
                        <small class="cj-field-error" id="receipt-error" style="display:none;" role="alert"></small>
                        <button type="submit" class="btn-cj w-100 mt-3" id="receipt-submit">
                            <i class="bi bi-upload"></i> {{ $order->receipt_path ? 'Upload New Receipt' : 'Upload Receipt' }}
                        </button>
                    </form>
                </div>
            </div>
        @endif

        @if($order->receipt_path)
            <p style="font-size:var(--fs-xs);color:var(--text-light);margin:.75rem 0 0;">
                <i class="bi bi-paperclip"></i>
                <a href="{{ route('orders.receipt', $order->id) }}" target="_blank" rel="noopener">View the receipt you uploaded</a>
                @if($order->gcash_reference) · Ref {{ $order->gcash_reference }} @endif
            </p>
        @endif
    </div>
</div>

@if($order->canUploadReceipt())
<script>
(function () {
    const form = document.getElementById('receipt-form');
    const input = document.getElementById('receipt-input');
    const previewWrap = document.getElementById('receipt-preview');
    const previewImg = document.getElementById('receipt-preview-img');
    const dropText = document.getElementById('receipt-drop-text');
    const errorEl = document.getElementById('receipt-error');
    const submitBtn = document.getElementById('receipt-submit');
    const submitHtml = submitBtn.innerHTML;
    let compressed = null;
    let sending = false;

    function showError(msg) { errorEl.textContent = msg; errorEl.style.display = 'block'; }
    function hideError() { errorEl.style.display = 'none'; }

    // Same approach as the rider's proof-of-delivery photo: ≤1280px on the
    // long edge, JPEG ~0.75 — a phone screenshot drops to ~150-300 KB.
    function compressImage(file) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const reader = new FileReader();
            reader.onload = (e) => { img.src = e.target.result; };
            reader.onerror = () => reject(new Error('read failed'));
            img.onload = () => {
                const MAX = 1280;
                let { width, height } = img;
                if (width > MAX || height > MAX) {
                    if (width > height) { height = Math.round(height * MAX / width); width = MAX; }
                    else { width = Math.round(width * MAX / height); height = MAX; }
                }
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#fff'; // transparent PNG screenshots → white, not black
                ctx.fillRect(0, 0, width, height);
                ctx.drawImage(img, 0, 0, width, height);
                canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('compression failed')), 'image/jpeg', 0.75);
            };
            img.onerror = () => reject(new Error('decode failed'));
            reader.readAsDataURL(file);
        });
    }

    input.addEventListener('change', async () => {
        const file = input.files[0];
        compressed = null;
        hideError();
        if (!file) return;
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
            showError('Please choose a JPG, PNG or WEBP image.');
            input.value = '';
            return;
        }
        try {
            compressed = await compressImage(file);
            previewImg.src = URL.createObjectURL(compressed);
            previewWrap.style.display = 'block';
            dropText.textContent = 'Tap to choose a different image';
        } catch (e) {
            showError('Could not read that image — please try another one.');
        }
    });

    form.addEventListener('submit', async (e) => {
        if (!window.fetch || !window.FormData) return; // let the plain form post the original file
        e.preventDefault();
        if (sending) return; // double-tap protection
        if (!compressed) { showError('Please choose a photo or screenshot of your GCash receipt.'); return; }

        sending = true;
        hideError();
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading…';

        const data = new FormData();
        data.append('_token', form.querySelector('input[name=_token]').value);
        data.append('receipt', compressed, 'gcash-receipt.jpg');
        data.append('gcash_reference', document.getElementById('receipt-ref').value);

        try {
            const resp = await fetch(form.action, { method: 'POST', headers: { 'Accept': 'application/json' }, body: data });
            const body = await resp.json().catch(() => ({}));
            if (resp.ok && body.redirect) { window.location.href = body.redirect; return; }
            showError(body.errors ? Object.values(body.errors)[0][0] : (body.message || 'Upload failed — please try again.'));
        } catch (err) {
            showError('Upload failed — check your connection and try again.');
        }
        // Keep the compressed image so a retry needs no re-selecting.
        sending = false;
        submitBtn.disabled = false;
        submitBtn.innerHTML = submitHtml;
    });
})();
</script>
@endif
@endif
