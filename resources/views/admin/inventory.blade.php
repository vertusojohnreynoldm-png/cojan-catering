<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Cojan Catering Admin</title>
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
        <h1 class="cj-page-title mb-0">Inventory Management</h1>
        <button class="btn-cj btn-cj" data-bs-toggle="modal" data-bs-target="#addInventoryModal">
            + Add Inventory
        </button>
    </div>
    <p class="cj-page-sub">Track stock levels for every menu item</p>

    <!-- Low Stock Alert -->
    @if($lowStock->count() > 0)
        <div class="alert-cj alert-danger" style="display:block;">
            <div>
                <h5 style="margin-bottom:.5rem;"><i class="bi bi-exclamation-triangle-fill"></i> Low Stock Alert!</h5>
                <ul class="mb-0">
                    @foreach($lowStock as $item)
                        <li>{{ $item->menuItem->name }} — only {{ $item->quantity }} {{ $item->unit }} left</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <!-- Inventory Table -->
    <div class="cj-card">
        <div class="cj-card-body p-0">
            <div class="table-responsive">
                <table class="cj-table">
                    <thead>
                        <tr>
                            <th>Menu Item</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Low Stock Threshold</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inventory as $item)
                            <tr>
                                <td>{{ $item->menuItem->name }}</td>
                                <td>{{ $item->menuItem->category->name }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>{{ $item->unit }}</td>
                                <td>{{ $item->low_stock_threshold }}</td>
                                <td>
                                    @if($item->isLowStock())
                                        <span class="badge-cj badge-lowstock">Low Stock</span>
                                    @else
                                        <span class="badge-cj badge-instock">In Stock</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <!-- Add Stock -->
                                        <form method="POST" action="{{ route('admin.inventory.add', $item->id) }}" class="d-flex gap-1">
                                            @csrf
                                            <input type="number" name="add_quantity" class="cj-input" style="width:70px;padding:.3rem .5rem;" min="1" value="1">
                                            <button type="submit" class="btn-cj btn-cj-sm">Add</button>
                                        </form>
                                        <!-- Edit -->
                                        <button class="btn-cj-amber btn-cj btn-cj-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editInventoryModal"
                                            data-id="{{ $item->id }}"
                                            data-quantity="{{ $item->quantity }}"
                                            data-threshold="{{ $item->low_stock_threshold }}"
                                            data-unit="{{ $item->unit }}">
                                            Edit
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4" style="color:var(--text-light)">No inventory records found. Add inventory to get started.</td>
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

<!-- Add Inventory Modal -->
<div class="modal fade" id="addInventoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Inventory</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('admin.inventory.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="cj-label">Menu Item <span style="color:#e74c3c;">*</span></label>
                        <select name="menu_item_id" class="cj-select" required>
                            <option value="">Select menu item</option>
                            @foreach(App\Models\MenuItem::with('category')->get() as $menuItem)
                                <option value="{{ $menuItem->id }}">{{ $menuItem->category->name }} - {{ $menuItem->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Quantity <span style="color:#e74c3c;">*</span></label>
                        <input type="number" name="quantity" class="cj-input" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Unit <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="unit" class="cj-input" placeholder="e.g. pcs, kg, liters" required>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Low Stock Threshold <span style="color:#e74c3c;">*</span></label>
                        <input type="number" name="low_stock_threshold" class="cj-input" min="1" value="10" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cj"
                            style="background:transparent;color:var(--green-dark);border-color:var(--green-dark);"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-cj btn-cj">Add Inventory</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Inventory Modal -->
<div class="modal fade" id="editInventoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Inventory</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editInventoryForm">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="cj-label">Quantity <span style="color:#e74c3c;">*</span></label>
                        <input type="number" name="quantity" id="editQuantity" class="cj-input" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Unit <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="unit" id="editUnit" class="cj-input" required>
                    </div>
                    <div class="mb-3">
                        <label class="cj-label">Low Stock Threshold <span style="color:#e74c3c;">*</span></label>
                        <input type="number" name="low_stock_threshold" id="editThreshold" class="cj-input" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cj"
                            style="background:transparent;color:var(--green-dark);border-color:var(--green-dark);"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-cj btn-cj">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])
<script>
    const editModal = document.getElementById('editInventoryModal');
    editModal.addEventListener('show.bs.modal', function(event) {
        const button   = event.relatedTarget;
        const id        = button.getAttribute('data-id');
        const quantity  = button.getAttribute('data-quantity');
        const threshold = button.getAttribute('data-threshold');
        const unit      = button.getAttribute('data-unit');

        document.getElementById('editQuantity').value  = quantity;
        document.getElementById('editThreshold').value = threshold;
        document.getElementById('editUnit').value      = unit;
        document.getElementById('editInventoryForm').action = `/admin/inventory/${id}`;
    });
</script>
</body>
</html>
