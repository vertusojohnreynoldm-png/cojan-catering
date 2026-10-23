{{-- Admin booking detail: the catering downpayment — breakdown, receipt,
     and Confirm / Reject. Included from admin/order-detail.blade.php only
     for orders with a downpayment ($order->requiresDownpayment()). --}}
<p class="mb-2"><strong>Payment</strong></p>
<x-downpayment-breakdown :total="$order->total_amount" :downpayment="$order->downpayment_amount"
                         :balance="$order->balanceAmount()"
                         :deadline="$order->isAwaitingDownpayment() ? $order->payment_due_at : null"
                         :paid-at="$order->downpayment_paid_at" />

<div class="cj-admin-receipt mt-3">
    @if($order->receipt_path)
        <a href="{{ route('orders.receipt', $order->id) }}" target="_blank" rel="noopener" class="cj-admin-receipt-thumb"
           aria-label="Open the GCash receipt full size">
            <img src="{{ route('orders.receipt', $order->id) }}"  alt="GCash receipt for #{{ $order->order_number }}">
        </a>
        <div style="min-width:0;">
            <p class="mb-1"><strong>GCash receipt</strong></p>
            <p class="mb-1" style="font-size:var(--fs-xs);color:var(--text-light);">
                Uploaded {{ $order->receipt_uploaded_at?->format('M d, Y g:i A') }}
            </p>
            @if($order->gcash_reference)
                <p class="mb-1" style="font-size:var(--fs-sm);">Ref: <code>{{ $order->gcash_reference }}</code></p>
            @endif
            @if($order->receipt_rejected_at)
                <span class="badge-cj badge-cancelled">Rejected: {{ $order->receipt_rejection_reason }}</span>
            @elseif($order->hasReceiptUnderReview())
                <span class="badge-cj badge-review">Waiting for your review</span>
            @endif
            <p class="mb-0 mt-1"><a href="{{ route('orders.receipt', $order->id) }}" target="_blank" rel="noopener" style="font-size:var(--fs-sm);">Open full size <i class="bi bi-box-arrow-up-right"></i></a></p>
        </div>
    @elseif($order->isAwaitingDownpayment())
        <p class="mb-0" style="font-size:var(--fs-sm);color:var(--text-light);">
            <i class="bi bi-hourglass"></i> No receipt uploaded yet. Auto-cancels {{ $order->payment_due_at->diffForHumans() }} if unpaid.
        </p>
    @endif
</div>

@if($order->status !== 'cancelled')
    <div class="d-flex gap-2 flex-wrap mt-3">
        @if($order->isAwaitingDownpayment())
            <form method="POST" action="{{ route('admin.orders.mark-paid', $order->id) }}"
                  onsubmit="return confirm('Confirm you received the ₱{{ number_format($order->downpayment_amount, 2) }} downpayment?');">
                @csrf
                <button type="submit" class="btn-cj btn-cj-sm"><i class="bi bi-check2-circle"></i> Confirm Downpayment</button>
            </form>
            @if($order->hasReceiptUnderReview())
                <button type="button" class="btn-cj btn-cj-sm" data-bs-toggle="modal" data-bs-target="#rejectReceiptModal"
                        style="background:transparent;color:var(--status-red-text);border-color:var(--status-red-text);">
                    <i class="bi bi-x-circle"></i> Reject Receipt
                </button>
            @endif
        @elseif($order->payment_status !== 'paid')
            <form method="POST" action="{{ route('admin.orders.mark-paid', $order->id) }}"
                  onsubmit="return confirm('Mark the ₱{{ number_format($order->balanceAmount(), 2) }} balance as paid?');">
                @csrf
                <button type="submit" class="btn-cj-amber btn-cj btn-cj-sm">Mark Balance Paid</button>
            </form>
        @else
            <span class="badge-cj badge-paid">Fully paid</span>
        @endif
    </div>
@endif

@if($order->hasReceiptUnderReview())
    @push('modals')
    <div class="modal fade" id="rejectReceiptModal" tabindex="-1" aria-labelledby="rejectReceiptTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectReceiptTitle">Reject GCash Receipt</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('admin.orders.reject-receipt', $order->id) }}">
                    <input type="hidden" name="_form" value="reject_receipt">
                    @csrf
                    <div class="modal-body">
                        <p style="font-size:var(--fs-sm);color:var(--text-mid);">
                            The customer is emailed your reason and can upload a new receipt. They get a fresh payment
                            window (never past the event start).
                        </p>
                        <label for="reject-reason" class="cj-label">What's wrong with it? <span style="color:var(--required);">*</span></label>
                        <input type="text" id="reject-reason" name="reason" class="cj-input" required maxlength="255"
                               placeholder="e.g. Amount is ₱500, expected ₱1,000" value="{{ old('reason') }}">
                        @error('reason')
                            <small class="cj-field-error">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cj"
                                style="background:transparent;color:var(--green-dark);border-color:var(--green-dark);"
                                data-bs-dismiss="modal">Back</button>
                        <button type="submit" class="btn-cj-danger btn-cj">Reject Receipt</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endpush
@endif
