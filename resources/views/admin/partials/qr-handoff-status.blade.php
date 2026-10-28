{{-- Admin: QR hand-off result for a MENU order delivery (nothing for bookings
     or while the hand-off simply hasn't happened yet). --}}
@if($delivery->order && !$delivery->order->isBooking())
    @if($delivery->qrVerified())
        <p class="cj-qr-verified mb-1">
            <i class="bi bi-qr-code-scan" aria-hidden="true"></i>
            <span>
                QR verified at hand-off at {{ $delivery->qr_scanned_at->format('g:i A') }}
                <span class="cj-qr-verified-meta">
                    {{ $delivery->qr_scanned_at->format('M d, Y') }}@if($delivery->qrScanner) · {{ $delivery->qrScanner->name }}@endif
                    @if($delivery->qr_scan_lat !== null && $delivery->qr_scan_lng !== null)
                        · {{ number_format($delivery->qr_scan_lat, 5) }}, {{ number_format($delivery->qr_scan_lng, 5) }}@if($delivery->qr_scan_accuracy) (±{{ round($delivery->qr_scan_accuracy) }}m)@endif
                    @else
                        · no GPS
                    @endif
                </span>
            </span>
        </p>
    @elseif($delivery->deliveredWithoutQr())
        <p class="cj-qr-unverified mb-1">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            Delivered without QR verification
        </p>
    @endif
@endif
