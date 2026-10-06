<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Menu Item - Cojan Catering Admin</title>
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
    <h1 class="cj-page-title">Edit Menu Item</h1>
    <p class="cj-page-sub">Update this dish's details</p>

    <div class="cj-card">
        <div class="cj-card-header">{{ $menuItem->name }}</div>
        <div class="cj-card-body">
            @if($errors->any())
                <div class="alert-cj alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.menu.update', $menuItem->id) }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="cj-label">Name <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="name" class="cj-input" value="{{ $menuItem->name }}" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="cj-label">Category <span style="color:#e74c3c;">*</span></label>
                        <select name="category_id" class="cj-select" required>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ $menuItem->category_id == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="cj-label">Price <span style="color:#e74c3c;">*</span></label>
                        <input type="number" name="price" class="cj-input" step="0.01" min="0" value="{{ $menuItem->price }}" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="cj-label">Image</label>
                        <input type="file" name="image" class="cj-input" accept="image/*">
                        @if($menuItem->image)
                            <small style="color:var(--text-light);">Current image: {{ $menuItem->image }}</small>
                        @endif
                    </div>
                    <div class="col-12">
                        <label class="cj-label">Description</label>
                        <textarea name="description" class="cj-textarea" rows="3">{{ $menuItem->description }}</textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="is_available" class="form-check-input" id="isAvailable" {{ $menuItem->is_available ? 'checked' : '' }}>
                            <label class="form-check-label" for="isAvailable">Available</label>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn-cj btn-cj">Save Changes</button>
                    <a href="{{ route('admin.menu') }}"
                       style="color:var(--green-dark);border:1.5px solid var(--green-dark);
                              border-radius:10px;padding:.45rem 1.1rem;text-decoration:none;font-size:.85rem;">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])
</body>
</html>
