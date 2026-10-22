<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tab === 'blocked' ? 'Blocked Dates' : 'Booking Rules' }} - Cojan Catering Admin</title>
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
    <h1 class="cj-page-title"><i class="bi bi-calendar2-check"></i> Booking Rules</h1>
    <p class="cj-page-sub">Capacity limits and closed dates — customers can't book past these, and the server re-checks every order.</p>

    <nav class="cj-tabs" aria-label="Booking rules sections">
        <a href="{{ route('admin.booking-rules') }}" class="cj-tab {{ $tab === 'rules' ? 'active' : '' }}"
           @if($tab === 'rules') aria-current="page" @endif>
            <i class="bi bi-sliders"></i> Limits
        </a>
        <a href="{{ route('admin.blocked-dates') }}" class="cj-tab {{ $tab === 'blocked' ? 'active' : '' }}"
           @if($tab === 'blocked') aria-current="page" @endif>
            <i class="bi bi-calendar-x"></i> Blocked Dates
        </a>
    </nav>

    @if($errors->any())
        <div class="alert-cj alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($tab === 'rules')
        <div class="cj-card" style="max-width:760px;">
            <div class="cj-card-header">Booking Limits</div>
            <div class="cj-card-body">
                <form method="POST" action="{{ route('admin.booking-rules.update') }}">
                    @csrf
                    @foreach($rules as $key => $rule)
                        <div class="cj-rule-row">
                            <div class="cj-rule-text">
                                <label for="rule-{{ $key }}" class="cj-label mb-1">{{ $rule['label'] }}</label>
                                <p class="cj-rule-help">{{ $rule['help'] }} <span class="text-nowrap">Default: {{ $rule['default'] }}.</span></p>
                            </div>
                            <div class="cj-rule-input">
                                <input type="number" id="rule-{{ $key }}" name="{{ $key }}" class="cj-input"
                                       inputmode="numeric" min="{{ $rule['min'] }}" max="{{ $rule['max'] }}" step="1" required
                                       value="{{ old($key, $values[$key]) }}">
                                @error($key)
                                    <small style="color:var(--danger);font-size:var(--fs-xs);display:block;margin-top:.3rem;">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>
                    @endforeach

                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn-cj">
                            <i class="bi bi-check2"></i> Save Rules
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @else
        <div class="row g-3">
            <div class="col-12 col-lg-5">
                <div class="cj-card">
                    <div class="cj-card-header">Block a Date</div>
                    <div class="cj-card-body">
                        <p style="font-size:var(--fs-sm);color:var(--text-light);">
                            No catering events, scheduled deliveries or ASAP orders can be placed on a blocked date.
                            Orders already on that date are <strong>not</strong> cancelled.
                        </p>
                        <form method="POST" action="{{ route('admin.blocked-dates.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="blocked-date" class="cj-label">Date <span style="color:var(--required);">*</span></label>
                                <input type="date" id="blocked-date" name="date" class="cj-input" required
                                       min="{{ now()->toDateString() }}" value="{{ old('date') }}">
                            </div>
                            <div class="mb-3">
                                <label for="blocked-reason" class="cj-label">Reason <span style="color:var(--required);">*</span></label>
                                <input type="text" id="blocked-reason" name="reason" class="cj-input" required maxlength="150"
                                       placeholder="e.g. Holy Week, staff outing" value="{{ old('reason') }}">
                                <small style="font-size:var(--fs-xs);color:var(--text-light);">Shown to customers.</small>
                            </div>
                            <button type="submit" class="btn-cj w-100">
                                <i class="bi bi-calendar-x"></i> Block Date
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-7">
                <div class="cj-card">
                    <div class="cj-card-header">Upcoming Blocked Dates</div>
                    <div class="cj-card-body p-0">
                        @forelse($upcoming as $blocked)
                            <div class="cj-blocked-item">
                                <div class="cj-blocked-date" aria-hidden="true">
                                    <span class="cj-blocked-month">{{ $blocked->day()->format('M') }}</span>
                                    <span class="cj-blocked-day">{{ $blocked->day()->format('d') }}</span>
                                </div>
                                <div class="cj-blocked-info">
                                    <div style="font-weight:600;">{{ $blocked->day()->format('l, M d, Y') }}</div>
                                    <div style="font-size:var(--fs-sm);color:var(--text-mid);overflow-wrap:anywhere;">{{ $blocked->reason }}</div>
                                    @if($activeOnDate[$blocked->id] > 0)
                                        <span class="badge-cj badge-pending mt-1" style="display:inline-block;">
                                            {{ $activeOnDate[$blocked->id] }} active {{ str('order')->plural($activeOnDate[$blocked->id]) }} on this date
                                        </span>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('admin.blocked-dates.destroy', $blocked->id) }}"
                                      onsubmit="return confirm('Unblock {{ $blocked->day()->format('M d, Y') }}? Customers will be able to book it again.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-cj btn-cj-sm"
                                            style="background:transparent;color:var(--green-dark);border-color:var(--green-dark);"
                                            aria-label="Unblock {{ $blocked->day()->format('M d, Y') }}">
                                        Unblock
                                    </button>
                                </form>
                            </div>
                        @empty
                            <div class="text-center py-4 px-3" style="color:var(--text-light);">
                                <div style="font-size:1.8rem;">📅</div>
                                No upcoming blocked dates — every date is open for booking.
                            </div>
                        @endforelse
                    </div>
                </div>
                @if($pastCount > 0)
                    <p style="font-size:var(--fs-xs);color:var(--text-light);margin-top:.5rem;">
                        {{ $pastCount }} past blocked {{ str('date')->plural($pastCount) }} hidden.
                    </p>
                @endif
            </div>
        </div>
    @endif

    <a href="{{ route('admin.dashboard') }}" class="cj-back-link">
        ← Back to Dashboard
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])
</body>
</html>
