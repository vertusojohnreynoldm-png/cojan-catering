@extends('layouts.customer')

@section('title', 'Account Notice — Cojan Catering')

@section('content')
<x-breadcrumbs :items="['Account Notice' => null]" />
<h1 class="cj-page-title">Account Notice</h1>
<p class="cj-page-sub">We're sorry for the inconvenience.</p>

<div style="max-width:720px;">
    @if(auth()->user()->isBlocked())
        <x-account-restricted-notice />
    @else
        <div class="alert-cj alert-success" role="status">
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            <span>Your account can place orders and bookings. <a href="{{ route('customer.menu') }}">Browse the menu →</a></span>
        </div>
    @endif
</div>
@endsection
