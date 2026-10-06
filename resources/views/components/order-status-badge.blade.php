@props(['order' => null, 'status' => null])
@php
    // Accept either a full Order instance or a bare status string.
    $target = $order ?? new \App\Models\Order(['status' => $status]);
@endphp
<span class="badge-cj {{ $target->statusBadgeClass() }}">{{ $target->statusLabel() }}</span>
