<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ratings - Cojan Catering Admin</title>
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

@php
    $returnUrl = request()->fullUrl();
    $filtered = collect($filters)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
@endphp

<div class="cj-page">
    <h1 class="cj-page-title">Ratings</h1>
    <p class="cj-page-sub">What customers think of the food and the service — reply, or hide anything inappropriate.</p>

    @if($errors->any())
        <div class="alert-cj alert-danger" role="alert">
            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    {{-- Summary --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-blue"><h5>Total ratings</h5><div class="stat-num">{{ $summary['total'] }}</div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-amber"><h5>Average overall</h5><div class="stat-num">{{ $summary['average'] ? number_format($summary['average'], 1) . ' ★' : '—' }}</div></div>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('admin.ratings', ['stars' => 'low', 'from' => now()->subDays(30)->toDateString()]) }}" class="stat-card stat-low d-block text-decoration-none">
                <h5>1–2 stars (30 days)</h5><div class="stat-num">{{ $summary['low30'] }}</div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('admin.ratings', ['visibility' => 'hidden']) }}" class="stat-card stat-teal d-block text-decoration-none">
                <h5>Hidden</h5><div class="stat-num">{{ $summary['hidden'] }}</div>
            </a>
        </div>
    </div>

    {{-- Averages --}}
    <div class="row g-3 mb-4">
        @foreach([
            ['By menu item', $itemAverages, 'item'],
            ['By package', $packageAverages, 'package'],
            ['By rider (delivery & rider score)', $riderAverages, 'rider'],
        ] as [$title, $rows, $param])
            <div class="col-12 col-lg-4">
                <div class="cj-card h-100">
                    <div class="cj-card-header">{{ $title }}</div>
                    <div class="cj-card-body">
                        @forelse($rows->take(6) as $row)
                            <a href="{{ route('admin.ratings', [$param => $row['id']]) }}" class="cj-avg-row">
                                <span class="cj-avg-name">{{ $row['name'] }}</span>
                                <x-rating-badge :stats="$row" />
                            </a>
                        @empty
                            <p class="mb-0" style="color:var(--text-light);">No ratings yet.</p>
                        @endforelse
                        @if($rows->count() > 6)
                            <details class="cj-avg-more">
                                <summary>Show all {{ $rows->count() }}</summary>
                                @foreach($rows->slice(6) as $row)
                                    <a href="{{ route('admin.ratings', [$param => $row['id']]) }}" class="cj-avg-row">
                                        <span class="cj-avg-name">{{ $row['name'] }}</span>
                                        <x-rating-badge :stats="$row" />
                                    </a>
                                @endforeach
                            </details>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <p class="cj-avg-note">Averages count visible ratings only, all time — the same numbers customers see.</p>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.ratings') }}" class="cj-card mb-3">
        <div class="cj-card-header">Filter ratings</div>
        <div class="cj-card-body">
            <div class="row g-2">
                <div class="col-12 col-sm-6 col-lg-4">
                    <label class="cj-label" for="f-item">Menu item</label>
                    <select id="f-item" name="item" class="cj-select">
                        <option value="">All items</option>
                        @foreach($menuItems as $item)
                            <option value="{{ $item->id }}" @selected(($filters['item'] ?? null) == $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                    <label class="cj-label" for="f-package">Package</label>
                    <select id="f-package" name="package" class="cj-select">
                        <option value="">All packages</option>
                        @foreach($packages as $package)
                            <option value="{{ $package->id }}" @selected(($filters['package'] ?? null) == $package->id)>{{ $package->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                    <label class="cj-label" for="f-rider">Rider</label>
                    <select id="f-rider" name="rider" class="cj-select">
                        <option value="">All riders</option>
                        @foreach($riders as $rider)
                            <option value="{{ $rider->id }}" @selected(($filters['rider'] ?? null) == $rider->id)>{{ $rider->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="cj-label" for="f-stars">Stars</label>
                    <select id="f-stars" name="stars" class="cj-select">
                        <option value="">Any</option>
                        <option value="low" @selected(($filters['stars'] ?? null) === 'low')>Low (1–2 anywhere)</option>
                        @foreach([5, 4, 3, 2, 1] as $s)
                            <option value="{{ $s }}" @selected(($filters['stars'] ?? null) == (string) $s)>{{ $s }} ★ overall</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="cj-label" for="f-visibility">Showing</label>
                    <select id="f-visibility" name="visibility" class="cj-select">
                        <option value="">Visible & hidden</option>
                        <option value="visible" @selected(($filters['visibility'] ?? null) === 'visible')>Visible only</option>
                        <option value="hidden" @selected(($filters['visibility'] ?? null) === 'hidden')>Hidden only</option>
                    </select>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="cj-label" for="f-from">From</label>
                    <input type="date" id="f-from" name="from" class="cj-input" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="col-6 col-lg-3">
                    <label class="cj-label" for="f-to">To</label>
                    <input type="date" id="f-to" name="to" class="cj-input" value="{{ $filters['to'] ?? '' }}">
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap mt-3">
                <button type="submit" class="btn-cj btn-cj"><i class="bi bi-funnel" aria-hidden="true"></i> Apply filters</button>
                @if($filtered)
                    <a href="{{ route('admin.ratings') }}" class="btn-cj-secondary btn-cj">Clear</a>
                @endif
            </div>
        </div>
    </form>

    <p class="cj-results-count" role="status">
        {{ $ratings->total() }} {{ \Illuminate\Support\Str::plural('rating', $ratings->total()) }}{{ $filtered ? ' match these filters' : '' }}
    </p>

    {{-- Ratings --}}
    @forelse($ratings as $rating)
        @php
            $order = $rating->order;
            $isBooking = $order?->isBooking();
            $labels = \App\Models\Feedback::serviceLabels((bool) $isBooking);
        @endphp
        <article id="rating-{{ $rating->id }}" class="cj-card cj-admin-rating mb-3 {{ $rating->isLow() ? 'is-low' : '' }} {{ $rating->is_hidden ? 'is-hidden' : '' }}">
            <div class="cj-card-body">
                <header class="cj-admin-rating-head">
                    <div>
                        <strong>{{ $rating->user?->name ?? 'Deleted customer' }}</strong>
                        @if($order)
                            · <a href="{{ route('admin.orders.show', $order->id) }}" class="cj-admin-rating-order">Order #{{ $order->order_number }}</a>
                        @endif
                        <div class="cj-admin-rating-meta">
                            {{ $rating->created_at->format('M j, Y g:i A') }}@if($rating->edited_at) · edited {{ $rating->edited_at->format('M j') }}@endif
                        </div>
                    </div>
                    <div class="cj-admin-rating-badges">
                        <span class="badge-cj {{ $isBooking ? 'badge-booking-type' : 'badge-confirmed' }}">{{ $isBooking ? '📦 Booking' : 'Menu order' }}</span>
                        @if($rating->isLow())
                            <span class="badge-cj badge-cancelled"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ $rating->lowestScore() }}★ score</span>
                        @endif
                        @if($rating->is_hidden)
                            <span class="badge-cj badge-blocked"><i class="bi bi-eye-slash" aria-hidden="true"></i> Hidden</span>
                        @endif
                    </div>
                </header>

                @if($rating->is_hidden)
                    <div class="alert-cj alert-warning mb-3">
                        <i class="bi bi-eye-slash" aria-hidden="true"></i>
                        <span>Hidden{{ $rating->hider ? ' by ' . $rating->hider->name : '' }}{{ $rating->hidden_at ? ' on ' . $rating->hidden_at->format('M j, Y') : '' }}: <strong>{{ $rating->hidden_reason }}</strong></span>
                    </div>
                @endif

                <div class="cj-admin-rating-grid">
                    <div>
                        @foreach($labels as $column => $label)
                            @if($rating->{$column})
                                <div class="cj-rating-row">
                                    <span>{{ $label }}@if($column === 'rating_service' && $rating->rider) ({{ $rating->rider->name }})@endif</span>
                                    <span class="cj-score {{ $rating->{$column} <= 2 ? 'is-low' : '' }}"><x-stars :value="$rating->{$column}" /> {{ $rating->{$column} }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    @if($rating->items->isNotEmpty())
                        <div>
                            @foreach($rating->items as $item)
                                <div class="cj-rating-row">
                                    <span>{{ $item->menuItem?->name ?? $item->package?->name ?? 'Removed item' }}</span>
                                    <span class="cj-score {{ $item->rating <= 2 ? 'is-low' : '' }}"><x-stars :value="$item->rating" /> {{ $item->rating }}</span>
                                </div>
                                @if($item->comment)
                                    <p class="cj-admin-item-comment">“{{ $item->comment }}”</p>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                @if($rating->comment)
                    <p class="cj-admin-rating-comment">“{{ $rating->comment }}”</p>
                @endif
                @if($rating->photo_path)
                    <a href="{{ route('ratings.photo', $rating) }}" target="_blank" rel="noopener" class="cj-review-photo-link">
                        <img src="{{ route('ratings.photo', $rating) }}" alt="Photo attached to this rating" loading="lazy" class="cj-review-photo">
                    </a>
                @endif

                @if($rating->admin_reply)
                    <div class="cj-rating-reply">
                        <strong style="font-size:var(--fs-sm);">Your reply</strong>
                        <span class="cj-admin-rating-meta">
                            {{ $rating->replier ? '— ' . $rating->replier->name : '' }}{{ $rating->replied_at ? ', ' . $rating->replied_at->format('M j, Y') : '' }}
                        </span>
                        <p class="mb-0 mt-1">{{ $rating->admin_reply }}</p>
                    </div>
                @endif

                <div class="cj-admin-rating-actions">
                    <details class="cj-admin-action">
                        <summary class="btn-cj-secondary btn-cj"><i class="bi bi-reply" aria-hidden="true"></i> {{ $rating->admin_reply ? 'Edit reply' : 'Reply' }}</summary>
                        <form method="POST" action="{{ route('admin.ratings.reply', $rating) }}" class="cj-admin-action-form">
                            @csrf
                            <input type="hidden" name="return" value="{{ $returnUrl }}">
                            <label class="cj-label" for="reply-{{ $rating->id }}">Reply (the customer sees this on their order and with the review)</label>
                            <textarea id="reply-{{ $rating->id }}" name="reply" rows="3" maxlength="1000" class="cj-textarea"
                                      placeholder="Thank them, or explain what you'll do better.">{{ $rating->admin_reply }}</textarea>
                            <div class="d-flex gap-2 align-items-center flex-wrap mt-2">
                                <button type="submit" class="btn-cj btn-cj">Save reply</button>
                                @if($rating->admin_reply)
                                    <small style="color:var(--text-light);">Clear the text and save to remove the reply.</small>
                                @endif
                            </div>
                        </form>
                    </details>

                    <details class="cj-admin-action">
                        @if($rating->is_hidden)
                            <summary class="btn-cj-secondary btn-cj"><i class="bi bi-eye" aria-hidden="true"></i> Unhide</summary>
                            <form method="POST" action="{{ route('admin.ratings.unhide', $rating) }}" class="cj-admin-action-form">
                                @csrf
                                <input type="hidden" name="return" value="{{ $returnUrl }}">
                                <label class="cj-label" for="reason-{{ $rating->id }}">Reason for showing it again <span style="color:var(--required);">*</span></label>
                                <input type="text" id="reason-{{ $rating->id }}" name="reason" class="cj-input" maxlength="255" required
                                       placeholder="e.g. Reviewed — no personal info after all">
                                <button type="submit" class="btn-cj btn-cj mt-2">Unhide rating</button>
                            </form>
                        @else
                            <summary class="btn-cj-danger btn-cj"><i class="bi bi-eye-slash" aria-hidden="true"></i> Hide</summary>
                            <form method="POST" action="{{ route('admin.ratings.hide', $rating) }}" class="cj-admin-action-form">
                                @csrf
                                <input type="hidden" name="return" value="{{ $returnUrl }}">
                                <label class="cj-label" for="reason-{{ $rating->id }}">Reason for hiding (kept in the activity log; the customer only sees "Hidden by Cojan") <span style="color:var(--required);">*</span></label>
                                <input type="text" id="reason-{{ $rating->id }}" name="reason" class="cj-input" maxlength="255" required
                                       placeholder="e.g. Contains a phone number">
                                <button type="submit" class="btn-cj-danger btn-cj mt-2">Hide rating</button>
                            </form>
                        @endif
                    </details>
                </div>
            </div>
        </article>
    @empty
        <div class="cj-card"><div class="cj-card-body text-center py-5" style="color:var(--text-light);">
            {{ $filtered ? 'No ratings match these filters.' : 'No ratings yet.' }}
        </div></div>
    @endforelse

    @if($ratings->hasPages())
        <nav class="cj-reviews-pager" aria-label="More ratings">
            @if($ratings->previousPageUrl())
                <a href="{{ $ratings->previousPageUrl() }}" class="btn-cj-secondary btn-cj">← Newer</a>
            @endif
            <span>Page {{ $ratings->currentPage() }} of {{ $ratings->lastPage() }}</span>
            @if($ratings->nextPageUrl())
                <a href="{{ $ratings->nextPageUrl() }}" class="btn-cj-secondary btn-cj">Older →</a>
            @endif
        </nav>
    @endif

    <a href="{{ route('admin.dashboard') }}" class="cj-back-link">← Back to Dashboard</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])
</body>
</html>
