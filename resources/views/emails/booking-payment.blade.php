@extends('emails.layout')

@section('subject', "Booking #{$order->order_number} — " . \App\Mail\BookingPaymentMail::SUBJECTS[$type])

@section('content')
@php
    $headline = [
        'received'  => 'Thanks — your booking is reserved for now',
        'confirmed' => 'Your downpayment is confirmed',
        'rejected'  => 'Please upload your GCash receipt again',
        'expired'   => 'Your booking was cancelled',
    ][$type];
@endphp
<p style="margin:0 0 16px;font-size:18px;font-weight:bold;color:#7A2E1D;">{{ $headline }}</p>

<p style="margin:0 0 16px;">
    Hi {{ $order->user->name }}, this is about your catering booking <strong>#{{ $order->order_number }}</strong>
    for <strong>{{ $order->event_date->format('l, M d, Y \a\t g:i A') }}</strong>.
</p>

@if($type === 'received')
    <p style="margin:0 0 16px;">
        To secure your date, please send the downpayment via GCash and upload a screenshot of the receipt on your booking page
        <strong>before {{ $order->payment_due_at->format('M d, Y g:i A') }}</strong>. Unpaid bookings are cancelled automatically after that.
    </p>
@elseif($type === 'confirmed')
    <p style="margin:0 0 16px;">
        We've received your downpayment — your date is secured. The remaining balance is paid on the event day, in cash or via GCash.
    </p>
@elseif($type === 'rejected')
    <p style="margin:0 0 16px;">
        We couldn't verify the receipt you uploaded.
        @if($order->receipt_rejection_reason)
            Reason: <strong>{{ $order->receipt_rejection_reason }}</strong>.
        @endif
        Please upload a clear screenshot of your GCash receipt <strong>before {{ $order->payment_due_at->format('M d, Y g:i A') }}</strong>.
    </p>
@else
    <p style="margin:0 0 16px;">
        We didn't receive the downpayment by {{ $order->payment_due_at->format('M d, Y g:i A') }}, so the booking was cancelled
        and the date was released. You're welcome to book again anytime — just reply to this email if you think this is a mistake.
    </p>
@endif

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;border:1px solid #f0ebe0;border-radius:8px;">
    <tr>
        <td style="padding:14px 16px;font-size:14px;">
            <p style="margin:0 0 6px;">Package total: <strong>&#8369;{{ number_format($order->total_amount, 2) }}</strong></p>
            <p style="margin:0 0 6px;">
                Downpayment (GCash): <strong>&#8369;{{ number_format($order->downpayment_amount, 2) }}</strong>
                @if($order->downpayment_paid_at)
                    — <span style="color:#166534;">paid</span>
                @endif
            </p>
            <p style="margin:0;">Balance on the event day: <strong>&#8369;{{ number_format($order->balanceAmount(), 2) }}</strong></p>
        </td>
    </tr>
</table>

@if(in_array($type, ['received', 'rejected'], true))
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0;">
        <tr>
            <td style="background:#E8A93B;border-radius:8px;">
                <a href="{{ route('customer.orders.show', $order->id) }}" style="display:inline-block;padding:12px 28px;color:#2B211D;font-weight:bold;text-decoration:none;font-size:14px;">Upload GCash Receipt &rarr;</a>
            </td>
        </tr>
    </table>
@endif

<p style="margin:16px 0 0;font-size:13px;color:#718096;">
    Questions about your booking? Just reply to this email or message us in the app.
</p>
@endsection
