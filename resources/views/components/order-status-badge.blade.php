@props(['order' => null, 'status' => null, 'showFlag' => false])
@php
    // Accept either a full Order instance or a bare status string.
    $target = $order ?? new \App\Models\Order(['status' => $status]);
@endphp
<span class="badge-cj {{ $target->statusBadgeClass() }}">{{ $target->statusLabel() }}</span>
{{-- Admin views only (show-flag): customers just see "Cancelled". --}}
@if($showFlag && $target->flagLabel())
    <span class="badge-cj badge-flag" title="{{ $target->cancellation_reason }}"><i class="bi bi-flag-fill" aria-hidden="true"></i> {{ $target->flagLabel() }}</span>
@endif
