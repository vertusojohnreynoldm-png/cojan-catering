<nav class="cj-nav">
    <a href="{{ route('admin.dashboard') }}" class="cj-nav-brand">🍽 Cojan <span>Admin</span></a>
    <div class="cj-nav-links">
        <a href="{{ route('admin.orders') }}" class="btn-cj-outline btn-cj btn-cj-sm">Orders</a>
        <a href="{{ route('admin.bookings') }}" class="btn-cj-outline btn-cj btn-cj-sm">📦 Bookings</a>
        <a href="{{ route('admin.menu') }}" class="btn-cj-outline btn-cj btn-cj-sm">Menu</a>
        <a href="{{ route('admin.packages') }}" class="btn-cj-outline btn-cj btn-cj-sm">Packages</a>
        <a href="{{ route('admin.inventory') }}" class="btn-cj-outline btn-cj btn-cj-sm">Inventory</a>
        <a href="{{ route('admin.utensils') }}" class="btn-cj-outline btn-cj btn-cj-sm">Utensils</a>
        <a href="{{ route('admin.analytics') }}" class="btn-cj-outline btn-cj btn-cj-sm">Analytics</a>
        <a href="{{ route('admin.feedback') }}" class="btn-cj-outline btn-cj btn-cj-sm">Feedback</a>
        <a href="{{ route('admin.users') }}" class="btn-cj-outline btn-cj btn-cj-sm">Users</a>
        <button type="button" class="btn-cj-amber btn-cj btn-cj-sm" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</button>
    </div>
</nav>
<x-logout-modal />
