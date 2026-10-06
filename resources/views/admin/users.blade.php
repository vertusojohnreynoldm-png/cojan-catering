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
                <table class="cj-table">
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
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <span class="badge-cj {{ $user->role == 'admin' ? 'badge-admin' : ($user->role == 'delivery' ? 'badge-delivery-role' : 'badge-customer') }}">
                                        {{ ucfirst($user->role) }}
                                    </span>
                                </td>
                                <td>{{ $user->phone ?? 'N/A' }}</td>
                                <td>{{ $user->created_at->format('M d, Y') }}</td>
                                <td>
                                    @if($user->id !== auth()->id())
                                        <a href="{{ route('admin.users.destroy', $user->id) }}"
                                           class="btn-cj-danger btn-cj btn-cj-sm"
                                           onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                                    @else
                                        <span style="color:var(--text-light);font-size:.8rem;">You</span>
                                    @endif
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

    <a href="{{ route('admin.dashboard') }}"
       style="display:inline-block;margin-top:1rem;color:var(--green-dark);border:1.5px solid var(--green-dark);
              border-radius:10px;padding:8px 18px;text-decoration:none;font-size:.9rem;">
        ← Back to Dashboard
    </a>
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
                        <label class="cj-label">Name <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="name" class="cj-input" required>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Email <span style="color:#e74c3c;">*</span></label>
                        <input type="email" name="email" class="cj-input" required>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Password <span style="color:#e74c3c;">*</span></label>
                        <input type="password" name="password" class="cj-input" required>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Role <span style="color:#e74c3c;">*</span></label>
                        <select name="role" class="cj-select" required>
                            <option value="customer">Customer</option>
                            <option value="delivery">Delivery</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Phone</label>
                        <input type="text" name="phone" class="cj-input">
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Address</label>
                        <textarea name="address" class="cj-textarea" rows="2"></textarea>
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
</body>
</html>
