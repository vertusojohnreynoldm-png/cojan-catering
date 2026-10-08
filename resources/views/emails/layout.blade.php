<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('subject', 'Cojan Catering Services')</title>
</head>
<body style="margin:0;padding:0;background:#FBF3E7;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FBF3E7;padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="background:#C1441E;padding:24px 28px;text-align:center;">
                        <span style="font-size:20px;font-weight:bold;color:#ffffff;font-family:Georgia,'Times New Roman',serif;">🍽 Cojan Catering</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;color:#2B211D;font-size:15px;line-height:1.6;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="background:#FBF3E7;padding:18px 28px;text-align:center;font-size:12px;color:#4a5568;">
                        <p style="margin:0 0 6px;">San Jose, Occidental Mindoro, Philippines</p>
                        <p style="margin:0 0 6px;">
                            <a href="https://www.facebook.com/share/1bNTNuqknZ/?mibextid=wwXIfr" style="color:#C1441E;text-decoration:none;">Follow us on Facebook</a>
                        </p>
                        <p style="margin:0;">&copy; {{ date('Y') }} Cojan Catering Services. All rights reserved.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
