<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management - Cojan Catering Admin</title>
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
        <h1 class="cj-page-title mb-0">Menu Management</h1>
        <button class="btn-cj btn-cj" data-bs-toggle="modal" data-bs-target="#addMenuModal">
            + Add Menu Item
        </button>
    </div>
    <p class="cj-page-sub">Manage the dishes customers can order</p>

    <div class="cj-card">
        <div class="cj-card-body p-0">
            <div class="table-responsive">
                <table class="cj-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Available</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($menuItems as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->category->name }}</td>
                                <td>₱{{ number_format($item->price, 2) }}</td>
                                <td>
                                    <span class="badge-cj {{ $item->is_available ? 'badge-instock' : 'badge-lowstock' }}">
                                        {{ $item->is_available ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td class="d-flex gap-1">
                                    <a href="{{ route('admin.menu.edit', $item->id) }}" class="btn-cj-amber btn-cj btn-cj-sm">Edit</a>
                                    <a href="{{ route('admin.menu.destroy', $item->id) }}"
                                       class="btn-cj-danger btn-cj btn-cj-sm"
                                       onclick="return confirm('Are you sure you want to delete this item?')">Delete</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4" style="color:var(--text-light)">No menu items found</td>
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

<!-- Add Menu Item Modal -->
<div class="modal fade" id="addMenuModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Menu Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('admin.menu.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="cj-label">Name <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="name" class="cj-input" required>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Category <span style="color:#e74c3c;">*</span></label>
                        <select name="category_id" class="cj-select" required>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Price <span style="color:#e74c3c;">*</span></label>
                        <input type="number" name="price" class="cj-input" step="0.01" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Description</label>
                        <textarea name="description" class="cj-textarea" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Image</label>
                        <input type="file" name="image" class="cj-input" accept="image/*">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_available" class="form-check-input" id="isAvailable" checked>
                        <label class="form-check-label" for="isAvailable">Available</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cj"
                            style="background:transparent;color:var(--green-dark);border-color:var(--green-dark);"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-cj btn-cj">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])
</body>
</html>
