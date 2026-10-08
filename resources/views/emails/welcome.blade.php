@extends('emails.layout')

@section('subject', 'Welcome to Cojan Catering Services!')

@section('content')
<p style="margin:0 0 16px;font-size:18px;font-weight:bold;color:#7A2E1D;">Welcome, {{ $user->name }}! 👋</p>

<p style="margin:0 0 16px;">
    Thanks for registering with <strong>Cojan Catering Services</strong> — we're glad to have you with us.
</p>

<p style="margin:0 0 10px;">Here's what you can do now:</p>
<ul style="margin:0 0 20px;padding-left:20px;">
    <li style="margin-bottom:8px;">🍽 Order fresh Filipino dishes from our menu</li>
    <li style="margin-bottom:8px;">📦 Book a catering package for your next event</li>
    <li style="margin-bottom:8px;">📍 Track your order live once it's on its way</li>
</ul>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0;">
    <tr>
        <td style="background:#E8A93B;border-radius:8px;">
            <a href="{{ route('customer.menu') }}" style="display:inline-block;padding:12px 28px;color:#2B211D;font-weight:bold;text-decoration:none;font-size:14px;">Browse the Menu &rarr;</a>
        </td>
    </tr>
</table>

<p style="margin:16px 0 0;font-size:13px;color:#718096;">
    If you didn't create this account, you can safely ignore this email.
</p>
@endsection
