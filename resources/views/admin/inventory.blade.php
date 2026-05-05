<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Cojan Catering Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="{{ route('admin.dashboard') }}">Cojan Catering - Admin</a>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.orders') }}" class="btn btn-outline-light btn-sm">Orders</a>
                <a href="{{ route('admin.menu') }}" class="btn btn-outline-light btn-sm">Menu</a>
                <a href="{{ route('admin.inventory') }}" class="btn btn-outline-light btn-sm">Inventory</a>
                <a href="{{ route('admin.users') }}" class="btn btn-outline-light btn-sm">Users</a>
                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">Logout</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Inventory Management</h2>
            <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addInventoryModal">
                + Add Inventory
            </button>
        </div>

        <!-- Low Stock Alert -->
        @if($lowStock->count() > 0)
            <div class="alert alert-danger mb-4">
                <h5>⚠ Low Stock Alert!</h5>
                <ul class="mb-0">
                    @foreach($lowStock as $item)
                        <li>{{ $item->menuItem->name }} — only {{ $item->quantity }} {{ $item->unit }} left</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Inventory Table -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
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
                                    <span class="badge bg-danger">Low Stock</span>
                                @else
                                    <span class="badge bg-success">In Stock</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <!-- Add Stock -->
                                    <form method="POST" action="{{ route('admin.inventory.add', $item->id) }}" class="d-flex gap-1">
                                        @csrf
                                        <input type="number" name="add_quantity" class="form-control form-control-sm" style="width: 70px;" min="1" value="1">
                                        <button type="submit" class="btn btn-sm btn-success">Add</button>
                                    </form>
                                    <!-- Edit -->
                                    <button class="btn btn-sm btn-warning"
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
                            <td colspan="7" class="text-center text-muted">No inventory records found. Add inventory to get started.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Inventory Modal -->
    <div class="modal fade" id="addInventoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Add Inventory</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('admin.inventory.store') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Menu Item <span class="text-danger">*</span></label>
                            <select name="menu_item_id" class="form-select" required>
                                <option value="">Select menu item</option>
                                @foreach(App\Models\MenuItem::with('category')->get() as $menuItem)
                                    <option value="{{ $menuItem->id }}">{{ $menuItem->category->name }} - {{ $menuItem->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Unit <span class="text-danger">*</span></label>
                            <input type="text" name="unit" class="form-control" placeholder="e.g. pcs, kg, liters" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Low Stock Threshold <span class="text-danger">*</span></label>
                            <input type="number" name="low_stock_threshold" class="form-control" min="1" value="10" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark">Add Inventory</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Inventory Modal -->
    <div class="modal fade" id="editInventoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Edit Inventory</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editInventoryForm">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" id="editQuantity" class="form-control" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Unit <span class="text-danger">*</span></label>
                            <input type="text" name="unit" id="editUnit" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Low Stock Threshold <span class="text-danger">*</span></label>
                            <input type="number" name="low_stock_threshold" id="editThreshold" class="form-control" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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