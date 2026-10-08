@extends('emails.layout')

@section('subject', 'Verify your email — Cojan Catering Services')

@section('content')
<p style="margin:0 0 16px;font-size:18px;font-weight:bold;color:#7A2E1D;">One last step, {{ $user->name }}</p>

<p style="margin:0 0 16px;">
    Please verify your email address to start ordering food and booking catering packages with Cojan Catering Services.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0;">
    <tr>
        <td style="background:#E8A93B;border-radius:8px;">
            <a href="{{ $verificationUrl }}" style="display:inline-block;padding:12px 28px;color:#2B211D;font-weight:bold;text-decoration:none;font-size:14px;">Verify My Email &rarr;</a>
        </td>
    </tr>
</table>

<p style="margin:0 0 10px;font-size:13px;color:#718096;">
    This link expires in 60 minutes. If it's expired, you can request a new one from the verification page after logging in.
</p>

<p style="margin:16px 0 0;font-size:13px;color:#718096;">
    If you didn't create an account with us, you can safely ignore this email.
</p>
@endsection
