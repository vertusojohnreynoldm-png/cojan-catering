<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catering Packages - Cojan Catering Admin</title>
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
        <h1 class="cj-page-title mb-0">Catering Packages</h1>
        <button class="btn-cj btn-cj" data-bs-toggle="modal" data-bs-target="#addPackageModal">
            + Add Package
        </button>
    </div>
    <p class="cj-page-sub">Pax-based deals bundling dishes with equipment</p>

    @if($errors->any())
        <div class="alert-cj alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="cj-card">
        <div class="cj-card-body p-0">
            <div class="table-responsive">
                <table class="cj-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Pax</th>
                            <th>Price</th>
                            <th>Includes</th>
                            <th>Available</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($packages as $package)
                            <tr>
                                <td>{{ $package->name }}</td>
                                <td>{{ $package->pax }}</td>
                                <td>₱{{ number_format($package->price, 2) }}</td>
                                <td style="font-size:.8rem;color:var(--text-light);max-width:280px;">
                                    {{ $package->menuItems->pluck('name')->join(', ') ?: '—' }}
                                </td>
                                <td>
                                    <span class="badge-cj {{ $package->is_available ? 'badge-instock' : 'badge-lowstock' }}">
                                        {{ $package->is_available ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td class="d-flex gap-1">
                                    <a href="{{ route('admin.packages.edit', $package->id) }}" class="btn-cj-amber btn-cj btn-cj-sm">Edit</a>
                                    <a href="{{ route('admin.packages.destroy', $package->id) }}"
                                       class="btn-cj-danger btn-cj btn-cj-sm"
                                       onclick="return confirm('Are you sure you want to delete this package?')">Delete</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4" style="color:var(--text-light)">No packages found</td>
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

<!-- Add Package Modal -->
<div class="modal fade" id="addPackageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Catering Package</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('admin.packages.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="cj-label">Name <span style="color:#e74c3c;">*</span></label>
                            <input type="text" name="name" class="cj-input" placeholder="e.g. 10 Pax Package" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="cj-label">Pax <span style="color:#e74c3c;">*</span></label>
                            <input type="number" name="pax" class="cj-input" min="1" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="cj-label">Price <span style="color:#e74c3c;">*</span></label>
                            <input type="number" name="price" class="cj-input" step="0.01" min="0" required>
                        </div>
                        <div class="col-12">
                            <label class="cj-label">Description</label>
                            <textarea name="description" class="cj-textarea" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="cj-label">Image</label>
                            <input type="file" name="image" class="cj-input" accept="image/*">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="cj-label">Included Menu Items <span style="color:#e74c3c;">*</span></label>
                            <p style="font-size:.78rem;color:var(--text-light);margin-bottom:.5rem;">Set a quantity for each dish to include; leave at 0 to exclude.</p>
                            <div style="max-height:220px;overflow-y:auto;border:1.5px solid #d1d5db;border-radius:6px;padding:.5rem .75rem;">
                                @foreach($menuItems as $menuItem)
                                    <div class="d-flex justify-content-between align-items-center py-1">
                                        <span style="font-size:.85rem;">{{ $menuItem->name }}</span>
                                        <input type="number" name="menu_item_quantities[{{ $menuItem->id }}]"
                                               class="cj-input" style="width:70px;padding:.3rem .5rem;" min="0" value="0">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="cj-label">Included Utensils <span style="color:#e74c3c;">*</span></label>
                            <p style="font-size:.78rem;color:var(--text-light);margin-bottom:.5rem;">Quantity needed per order of this package.</p>
                            <div style="max-height:220px;overflow-y:auto;border:1.5px solid #d1d5db;border-radius:6px;padding:.5rem .75rem;">
                                @foreach($utensils as $utensil)
                                    <div class="d-flex justify-content-between align-items-center py-1">
                                        <span style="font-size:.85rem;">{{ $utensil->name }}</span>
                                        <input type="number" name="utensil_quantities[{{ $utensil->id }}]"
                                               class="cj-input" style="width:70px;padding:.3rem .5rem;" min="0" value="0">
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_available" class="form-check-input" id="isAvailable" checked>
                                <label class="form-check-label" for="isAvailable">Available</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cj"
                            style="background:transparent;color:var(--green-dark);border-color:var(--green-dark);"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-cj btn-cj">Add Package</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])
</body>
</html>
