{{--
    Package total → GCash downpayment (with its deadline) → balance on the
    event day. Used on package checkout (before the booking exists) and on
    the customer + admin booking detail pages.

    Props: total, downpayment, balance (floats), deadline (Carbon|null),
           percent (int|null), paidAt (Carbon|null — downpayment confirmed)
--}}
@props(['total', 'downpayment', 'balance', 'deadline' => null, 'percent' => null, 'paidAt' => null])

<dl class="cj-dp">
    <div class="cj-dp-row">
        <dt>Package total</dt>
        <dd>₱{{ number_format($total, 2) }}</dd>
    </div>
    <div class="cj-dp-row cj-dp-now">
        <dt>
            Downpayment{{ $percent ? " ({$percent}%)" : '' }} <span class="cj-dp-via">via GCash</span>
            @if($paidAt)
                <span class="badge-cj badge-delivered"><i class="bi bi-check2"></i> Paid</span>
            @elseif($deadline)
                <small class="cj-dp-deadline"><i class="bi bi-alarm"></i> Pay by {{ $deadline->format('D, M d, Y g:i A') }}</small>
            @endif
        </dt>
        <dd>₱{{ number_format($downpayment, 2) }}</dd>
    </div>
    <div class="cj-dp-row">
        <dt>
            Balance on the event day
            <small class="cj-dp-sub">Cash or GCash</small>
        </dt>
        <dd>₱{{ number_format($balance, 2) }}</dd>
    </div>
</dl>
