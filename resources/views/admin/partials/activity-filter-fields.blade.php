{{-- Shared by both the desktop inline filter row and the mobile filter
     sheet in admin/activity.blade.php — one set of fields, two containers. --}}
<div class="mb-3">
    <label class="cj-label">From</label>
    <input type="date" name="from" value="{{ request('from') }}" class="cj-input">
</div>
<div class="mb-3">
    <label class="cj-label">To</label>
    <input type="date" name="to" value="{{ request('to') }}" class="cj-input">
</div>
<div class="mb-3">
    <label class="cj-label">Actor</label>
    <select name="actor_id" class="cj-select">
        <option value="">All actors</option>
        @foreach($actors as $actor)
            <option value="{{ $actor->id }}" {{ request('actor_id') == $actor->id ? 'selected' : '' }}>
                {{ $actor->name }}
            </option>
        @endforeach
    </select>
</div>
<div class="mb-3">
    <label class="cj-label">Role</label>
    <select name="role" class="cj-select">
        <option value="">All roles</option>
        <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
        <option value="customer" {{ request('role') === 'customer' ? 'selected' : '' }}>Customer</option>
        <option value="delivery" {{ request('role') === 'delivery' ? 'selected' : '' }}>Delivery</option>
        <option value="system" {{ request('role') === 'system' ? 'selected' : '' }}>System</option>
    </select>
</div>
<div class="mb-3">
    <label class="cj-label">Category</label>
    <select name="category" class="cj-select">
        <option value="">All categories</option>
        <option value="auth" {{ request('category') === 'auth' ? 'selected' : '' }}>Auth</option>
        <option value="orders" {{ request('category') === 'orders' ? 'selected' : '' }}>Orders &amp; Bookings</option>
        <option value="admin" {{ request('category') === 'admin' ? 'selected' : '' }}>Admin Changes</option>
        <option value="stock" {{ request('category') === 'stock' ? 'selected' : '' }}>Stock</option>
    </select>
</div>
<div class="mb-3">
    <label class="cj-label">Search</label>
    <input type="text" name="search" value="{{ request('search') }}" class="cj-input" placeholder="Search description...">
</div>
