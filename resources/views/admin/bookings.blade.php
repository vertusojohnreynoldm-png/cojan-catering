<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catering Bookings - Cojan Catering Admin</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/cojan.css') }}">
</head>
<body>
<x-admin-nav />
<x-toast />

<div class="cj-page">
    <h1 class="cj-page-title">📦 Catering Bookings</h1>
    <p class="cj-page-sub">Events booked through the Avail flow, soonest first</p>

    <div class="cj-card">
        <div class="cj-card-body p-0">
            <div class="table-responsive">
                <table class="cj-table">
                    <thead>
                        <tr>
                            <th>Contact</th>
                            <th>Event Date</th>
                            <th>Venue</th>
                            <th>Package</th>
                            <th>Pax</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bookings as $booking)
                            @php
                                $eventDate = $booking->event_date;
                                $daysUntil = now()->startOfDay()->diffInDays($eventDate->copy()->startOfDay(), false);

                                if ($daysUntil < 0) {
                                    $whenLabel = abs($daysUntil) . ' day' . (abs($daysUntil) === 1 ? '' : 's') . ' ago';
                                    $whenStyle = 'color:var(--text-light);';
                                } elseif ($daysUntil === 0) {
                                    $whenLabel = 'Today';
                                    $whenStyle = 'color:var(--danger);font-weight:700;';
                                } elseif ($daysUntil === 1) {
                                    $whenLabel = 'Tomorrow';
                                    $whenStyle = 'color:#92400e;font-weight:600;';
                                } else {
                                    $whenLabel = 'in ' . $daysUntil . ' days';
                                    $whenStyle = $daysUntil <= 3 ? 'color:#92400e;font-weight:600;' : 'color:var(--text-mid);';
                                }

                                $isUrgent = $daysUntil >= 0 && $daysUntil <= 3 && $booking->status === 'pending';
                                $package = $booking->orderItems->first()?->package;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $booking->contact_name ?? $booking->user->name }}</strong><br>
                                    <small style="color:var(--text-light);">{{ $booking->delivery_phone }}</small>
                                </td>
                                <td>
                                    {{ $eventDate->format('M d, Y') }}<br>
                                    <small style="font-size:.72rem;{{ $whenStyle }}">
                                        {{ $eventDate->format('h:i A') }} · {{ $whenLabel }}
                                        @if($isUrgent)
                                            <i class="bi bi-exclamation-triangle-fill" title="Event soon, still pending confirmation"></i>
                                        @endif
                                    </small>
                                </td>
                                <td>{{ Str::limit($booking->delivery_address, 40) }}</td>
                                <td>{{ $package->name ?? '—' }}</td>
                                <td>{{ $package->pax ?? '—' }}</td>
                                <td>₱{{ number_format($booking->total_amount, 2) }}</td>
                                <td>
                                    <x-order-status-badge :order="$booking" />
                                </td>
                                <td>
                                    <a href="{{ route('admin.orders.show', $booking->id) }}" class="btn-cj btn-cj-sm">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4" style="color:var(--text-light)">No catering bookings yet</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <a href="{{ route('admin.dashboard') }}"
       style="display:inline-block;margin-top:1rem;color:var(--green-dark);border:1.5px solid var(--green-dark);
              border-radius:10px;padding:8px 18px;text-decoration:none;font-size:.9rem;">
        ← Back to Dashboard
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])
</body>
</html>
