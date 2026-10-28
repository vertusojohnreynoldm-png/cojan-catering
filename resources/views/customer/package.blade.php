@extends('layouts.customer')

@section('title', $package->name . ' — Cojan Catering')

@section('content')
<x-breadcrumbs :items="['Catering Packages' => route('customer.packages.index'), $package->name => null]" />

<div class="cj-card cj-item-hero">
    <div class="cj-item-hero-img">
        @if($package->image)
            <img src="{{ asset('storage/' . $package->image) }}" alt="{{ $package->name }}">
        @else
            <span>🍽 No Image</span>
        @endif
    </div>
    <div class="cj-item-hero-body">
        <div class="cj-item-category">📦 Package deal · {{ $package->pax }} pax</div>
        <h1 class="cj-page-title mb-1">{{ $package->name }}</h1>
        <a href="#reviews-heading" class="cj-item-rating-link">
            <x-rating-badge :stats="$stats" />
        </a>
        @if($package->description)
            <p class="cj-item-desc">{{ $package->description }}</p>
        @endif

        @if($package->menuItems->isNotEmpty())
            <div class="cj-item-included">
                <div class="cj-item-included-title"><i class="bi bi-check2-circle" aria-hidden="true"></i> What's included</div>
                <div class="d-flex flex-wrap gap-1">
                    @foreach($package->menuItems as $item)
                        <span class="cj-chip">{{ $item->pivot->quantity }}x {{ $item->name }}</span>
                    @endforeach
                </div>
            </div>
        @endif
        @if($package->utensils->isNotEmpty())
            <div class="cj-item-included">
                <div class="cj-item-included-title"><i class="bi bi-tools" aria-hidden="true"></i> Equipment provided</div>
                <div class="d-flex flex-wrap gap-1">
                    @foreach($package->utensils as $utensil)
                        <span class="cj-chip cj-chip-muted">{{ $utensil->pivot->quantity_needed }} {{ $utensil->name }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="menu-card-price">₱{{ number_format($package->price, 2) }}</div>
        <a href="{{ route('customer.packages.index', ['avail' => $package->id]) }}" class="btn-cj-amber btn-cj cj-item-cta">
            Avail This Package
        </a>
    </div>
</div>

<x-review-list :stats="$stats" :reviews="$reviews" :breakdown="$breakdown" subject="this package" />

<a href="{{ route('customer.packages.index') }}" class="cj-back-link">← Back to Packages</a>
@endsection
