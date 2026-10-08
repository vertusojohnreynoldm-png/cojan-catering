@extends('emails.layout')

@section('subject', "Order #{$order->order_number} — {$order->statusLabel()}")

@section('content')
<p style="margin:0 0 16px;font-size:18px;font-weight:bold;color:#7A2E1D;">
    Your {{ $order->isBooking() ? 'booking' : 'order' }} is now {{ strtolower($order->statusLabel()) }}
</p>

<p style="margin:0 0 16px;">
    Hi {{ $order->user->name }}, here's an update on your
    {{ $order->isBooking() ? 'catering booking' : 'order' }}
    <strong>#{{ $order->order_number }}</strong>.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;border:1px solid #f0ebe0;border-radius:8px;">
    <tr>
        <td style="padding:14px 16px;">
            @foreach($order->orderItems as $item)
                <p style="margin:0 0 6px;font-size:14px;">{{ $item->displayName() }} x{{ $item->quantity }} — &#8369;{{ number_format($item->subtotal, 2) }}</p>
            @endforeach
            <p style="margin:10px 0 0;font-weight:bold;color:#7A2E1D;">Total: &#8369;{{ number_format($order->total_amount, 2) }}</p>
        </td>
    </tr>
</table>

@unless($order->isBooking())
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0;">
        <tr>
            <td style="background:#E8A93B;border-radius:8px;">
                <a href="{{ route('customer.orders.track', $order->order_number) }}" style="display:inline-block;padding:12px 28px;color:#2B211D;font-weight:bold;text-decoration:none;font-size:14px;">Track Your Order &rarr;</a>
            </td>
        </tr>
    </table>
@endunless

<p style="margin:16px 0 0;font-size:13px;color:#718096;">
    Questions about your {{ $order->isBooking() ? 'booking' : 'order' }}? Just reply to this email or reach out through the app.
</p>
@endsection
