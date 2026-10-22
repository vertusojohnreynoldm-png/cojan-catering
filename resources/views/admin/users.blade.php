<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Cojan Catering Admin</title>
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
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
        <h1 class="cj-page-title mb-0">User Management</h1>
        <button class="btn-cj btn-cj" data-bs-toggle="modal" data-bs-target="#addUserModal">
            + Add User
        </button>
    </div>
    <p class="cj-page-sub">Manage admin, customer, and delivery accounts</p>

    <div class="cj-card">
        <div class="cj-card-body p-0">
            <div class="table-responsive">
                <table class="cj-table cj-table-stack">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Phone</th>
                            <th>Joined</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td data-label="Name">
                                    <span>
                                        {{ $user->name }}
                                        @if($user->isBlocked())
                                            <br><span class="badge-cj badge-blocked"><i class="bi bi-slash-circle"></i> Blocked</span>
                                        @endif
                                    </span>
                                </td>
                                <td data-label="Email">{{ $user->email }}</td>
                                <td data-label="Role">
                                    <span class="badge-cj {{ $user->role == 'admin' ? 'badge-admin' : ($user->role == 'delivery' ? 'badge-delivery-role' : 'badge-customer') }}">
                                        {{ ucfirst($user->role) }}
                                    </span>
                                </td>
                                <td data-label="Phone">{{ $user->phone ?? 'N/A' }}</td>
                                <td data-label="Joined">{{ $user->created_at->format('M d, Y') }}</td>
                                <td data-label="" class="cj-td-actions">
                                    @if($user->isBlocked())
                                        <div class="cj-blocked-note">
                                            <small>Blocked {{ $user->blocked_at->format('M d, Y') }}: {{ $user->blocked_reason }}</small>
                                        </div>
                                    @endif
                                    <div class="d-flex gap-2 flex-wrap">
                                        @if($user->isCustomer())
                                            @if($user->isBlocked())
                                                <form method="POST" action="{{ route('admin.users.unblock', $user->id) }}"
                                                      onsubmit="return confirm('Let {{ addslashes($user->name) }} place orders again?')">
                                                    @csrf
                                                    <button type="submit" class="btn-cj btn-cj-sm">Unblock</button>
                                                </form>
                                            @else
                                                <button type="button" class="btn-cj btn-cj-sm"
                                                        style="background:transparent;color:var(--status-red-text);border-color:var(--status-red-text);"
                                                        data-bs-toggle="modal" data-bs-target="#blockUserModal"
                                                        data-user-id="{{ $user->id }}" data-user-name="{{ $user->name }}">
                                                    Block
                                                </button>
                                            @endif
                                        @endif
                                        @if($user->id !== auth()->id())
                                            <a href="{{ route('admin.users.destroy', $user->id) }}"
                                               class="btn-cj-danger btn-cj btn-cj-sm"
                                               onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                                        @else
                                            <span style="color:var(--text-light);font-size:.8rem;">You</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4" style="color:var(--text-light)">No users found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <a href="{{ route('admin.dashboard') }}" class="cj-back-link">
        ← Back to Dashboard
    </a>
</div>

<!-- Block Customer Modal (one shared modal, filled per row via data-*) -->
<div class="modal fade" id="blockUserModal" tabindex="-1" aria-labelledby="blockUserTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="blockUserTitle">Block <span id="block-user-name">customer</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="block-user-form"
                  action="{{ old('block_user_id') ? route('admin.users.block', old('block_user_id')) : '#' }}">
                @csrf
                <input type="hidden" name="block_user_id" id="block-user-id" value="{{ old('block_user_id') }}">
                <div class="modal-body">
                    <p style="font-size:var(--fs-sm);color:var(--text-mid);">
                        They can still log in, see their orders and chat with you, but can't place new orders or bookings.
                        They see a polite notice, never your reason.
                    </p>
                    <label for="block-reason" class="cj-label">Reason (admins only) <span style="color:var(--required);">*</span></label>
                    <input type="text" id="block-reason" name="reason" class="cj-input" required maxlength="255"
                           placeholder="e.g. 3 fake bookings in October" value="{{ old('reason') }}">
                    @error('reason')
                        <small style="color:var(--danger);font-size:.78rem;display:block;margin-top:.3rem;">{{ $message }}</small>
                    @enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cj"
                            style="background:transparent;color:var(--green-dark);border-color:var(--green-dark);"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-cj-danger btn-cj">Block Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="cj-label">Name <span style="color:var(--required);">*</span></label>
                        <input type="text" name="name" class="cj-input" required value="{{ old('name') }}">
                        @error('name')
                            <small style="color:var(--danger);font-size:.78rem;display:block;margin-top:.3rem;">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Email <span style="color:var(--required);">*</span></label>
                        <input type="email" name="email" class="cj-input" required value="{{ old('email') }}">
                        @error('email')
                            <small style="color:var(--danger);font-size:.78rem;display:block;margin-top:.3rem;">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Password <span style="color:var(--required);">*</span></label>
                        <input type="password" name="password" class="cj-input" required>
                        @error('password')
                            <small style="color:var(--danger);font-size:.78rem;display:block;margin-top:.3rem;">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Role <span style="color:var(--required);">*</span></label>
                        <select name="role" class="cj-select" required>
                            <option value="customer" {{ old('role') === 'customer' ? 'selected' : '' }}>Customer</option>
                            <option value="delivery" {{ old('role') === 'delivery' ? 'selected' : '' }}>Delivery</option>
                            <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Phone</label>
                        <input type="tel" name="phone" class="cj-input" inputmode="numeric" maxlength="13"
                               placeholder="09XXXXXXXXX" value="{{ old('phone') }}">
                        @error('phone')
                            <small style="color:var(--danger);font-size:.78rem;display:block;margin-top:.3rem;">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Address</label>
                        <textarea name="address" class="cj-textarea" rows="2">{{ old('address') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cj"
                            style="background:transparent;color:var(--green-dark);border-color:var(--green-dark);"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-cj btn-cj">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])
<script>
(function () {
    const modal = document.getElementById('blockUserModal');
    const form = document.getElementById('block-user-form');
    const blockUrl = @json(route('admin.users.block', '__ID__'));
    modal.addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        if (!btn || !btn.dataset.userId) return;
        form.action = blockUrl.replace('__ID__', btn.dataset.userId);
        document.getElementById('block-user-id').value = btn.dataset.userId;
        document.getElementById('block-user-name').textContent = btn.dataset.userName;
    });
})();
</script>
@if($errors->any())
    <script>
        new bootstrap.Modal(document.getElementById(@json(old('block_user_id') ? 'blockUserModal' : 'addUserModal'))).show();
    </script>
@endif
</body>
</html>
