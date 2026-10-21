<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - Cojan Catering Admin</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/cojan.css') }}">
    <style>
        /* ── Desktop (default): inline filter row, table ── */
        .cj-activity-filters-desktop { display: flex; gap: .6rem; flex-wrap: wrap; align-items: flex-end; margin-bottom: 1.25rem; }
        .cj-activity-filters-desktop .mb-3 { margin-bottom: 0 !important; min-width: 150px; }
        .cj-activity-mobile-bar { display: none; }
        .cj-activity-cards { display: none; }

        .cj-activity-details-row { display: none; }
        .cj-activity-details-row.open { display: table-row; }
        .cj-activity-details { background: var(--cream); border-radius: var(--radius-sm); padding: .75rem 1rem; font-size: .82rem; }
        .cj-activity-diff-field { display: flex; gap: .5rem; flex-wrap: wrap; padding: .2rem 0; }
        .cj-activity-diff-field b { min-width: 120px; }

        @media (max-width: 768px) {
            .cj-activity-filters-desktop { display: none; }
            .cj-activity-table-wrap { display: none; }
            .cj-activity-mobile-bar { display: flex; gap: .6rem; margin-bottom: 1rem; }
            .cj-activity-cards { display: flex; flex-direction: column; gap: .75rem; }

            .cj-activity-card {
                background: var(--white);
                border-radius: var(--radius-md);
                box-shadow: var(--shadow-sm);
                padding: 1rem;
            }
            .cj-activity-card-top { display: flex; justify-content: space-between; align-items: center; gap: .5rem; margin-bottom: .5rem; }
            .cj-activity-card-desc { font-size: .92rem; margin-bottom: .4rem; }
            .cj-activity-card-meta { font-size: .78rem; color: var(--text-light); display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
            .cj-activity-card-details-btn { min-height: 44px; min-width: 44px; background: none; border: none; color: var(--green-dark); }
            .cj-activity-card-details { display: none; margin-top: .6rem; }
            .cj-activity-card-details.open { display: block; }

            /* Filters sheet modal fields need the same 44px/16px mobile
               sizing as everywhere else in this pass — .cj-input/.cj-select
               already get that from the global mobile rules in cojan.css. */
            #activityFiltersModal .modal-footer .btn-cj { flex: 1; }
        }
    </style>
</head>
<body>
<x-admin-nav />
<x-toast />

<div class="cj-page">
    <h1 class="cj-page-title">Activity Log</h1>
    <p class="cj-page-sub">Who did what, when, and from where — a read-only audit trail.</p>

    {{-- Desktop: inline filter row --}}
    <form method="GET" action="{{ route('admin.activity') }}" class="cj-activity-filters-desktop">
        @include('admin.partials.activity-filter-fields')
        <div class="mb-3 d-flex gap-2">
            <button type="submit" class="btn-cj btn-cj-sm">Filter</button>
            <a href="{{ route('admin.activity') }}" class="btn-cj-outline btn-cj btn-cj-sm">Clear</a>
            <a href="{{ route('admin.activity.export', request()->query()) }}" class="btn-cj-amber btn-cj btn-cj-sm">
                <i class="bi bi-download"></i> Export CSV
            </a>
        </div>
    </form>

    {{-- Mobile: collapsed Filters button + sheet --}}
    <div class="cj-activity-mobile-bar">
        <button type="button" class="btn-cj-outline btn-cj" data-bs-toggle="modal" data-bs-target="#activityFiltersModal" style="flex:1;">
            <i class="bi bi-funnel"></i> Filters
        </button>
        <a href="{{ route('admin.activity.export', request()->query()) }}" class="btn-cj-amber btn-cj" aria-label="Export CSV">
            <i class="bi bi-download"></i>
        </a>
    </div>

    <div class="modal fade" id="activityFiltersModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Filters</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="GET" action="{{ route('admin.activity') }}">
                    <div class="modal-body">
                        @include('admin.partials.activity-filter-fields')
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('admin.activity') }}" class="btn-cj-outline btn-cj">Clear</a>
                        <button type="submit" class="btn-cj-amber btn-cj">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Desktop table --}}
    <div class="cj-card cj-activity-table-wrap">
        <div class="cj-card-body p-0">
            <div class="table-responsive">
                <table class="cj-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Actor</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Subject</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td title="{{ $log->created_at->timezone('Asia/Manila')->format('M d, Y g:i A') }}">
                                    {{ $log->created_at->diffForHumans() }}
                                </td>
                                <td>{{ $log->actor_name }} <small style="color:var(--text-light);">({{ $log->actor_role }})</small></td>
                                <td><span class="badge-cj badge-activity-{{ $log->category }}">{{ ucfirst($log->category) }}</span></td>
                                <td>{{ $log->description }}</td>
                                <td>{{ $log->subject_label ?? '—' }}</td>
                                <td>
                                    @if($log->changes)
                                        <button type="button" class="btn-cj btn-cj-sm" onclick="toggleActivityDetails({{ $log->id }})">
                                            Details
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @if($log->changes)
                                <tr class="cj-activity-details-row" id="activity-details-row-{{ $log->id }}">
                                    <td colspan="6">
                                        <div class="cj-activity-details">
                                            @foreach($log->changes as $field => $vals)
                                                <div class="cj-activity-diff-field">
                                                    <b>{{ $field }}:</b>
                                                    <span>{{ $vals['old'] ?? '—' }} → {{ $vals['new'] ?? '—' }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="6" class="text-center py-4" style="color:var(--text-light)">No activity recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Mobile cards --}}
    <div class="cj-activity-cards">
        @forelse($logs as $log)
            <div class="cj-activity-card">
                <div class="cj-activity-card-top">
                    <span class="badge-cj badge-activity-{{ $log->category }}">{{ ucfirst($log->category) }}</span>
                    <span style="font-size:.78rem;color:var(--text-light);" title="{{ $log->created_at->timezone('Asia/Manila')->format('M d, Y g:i A') }}">
                        {{ $log->created_at->diffForHumans() }}
                    </span>
                </div>
                <div class="cj-activity-card-desc">{{ $log->description }}</div>
                <div class="cj-activity-card-meta">
                    <span>{{ $log->actor_name }} ({{ $log->actor_role }})</span>
                    @if($log->changes)
                        <button type="button" class="cj-activity-card-details-btn" onclick="toggleActivityDetails('m{{ $log->id }}')" aria-label="Show details">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    @endif
                </div>
                @if($log->changes)
                    <div class="cj-activity-card-details" id="activity-details-row-m{{ $log->id }}">
                        <div class="cj-activity-details">
                            @foreach($log->changes as $field => $vals)
                                <div class="cj-activity-diff-field">
                                    <b>{{ $field }}:</b>
                                    <span>{{ $vals['old'] ?? '—' }} → {{ $vals['new'] ?? '—' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="cj-card"><div class="cj-card-body text-center" style="color:var(--text-light);">No activity recorded yet.</div></div>
        @endforelse
    </div>

    <div class="mt-3">
        {{ $logs->links('pagination::bootstrap-5') }}
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])

<script>
function toggleActivityDetails(id) {
    const row = document.getElementById('activity-details-row-' + id);
    if (!row) return;
    row.classList.toggle('open');
}
</script>
</body>
</html>
