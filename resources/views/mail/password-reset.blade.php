<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __("messages.auth.reset_password") }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #070715; font-family: Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #070715; padding: 40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background-color: #1f2937; border-radius: 8px; border: 1px solid #374151;">
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h1 style="color: #ffffff; font-size: 24px; margin: 0 0 16px 0;">
                                {{ __("messages.auth.reset_password") }}
                            </h1>
                            <p style="color: #C3C3D1; font-size: 16px; line-height: 1.5; margin: 0 0 24px 0;">
                                Hello {{ $userName }},
                            </p>
                            <p style="color: #C3C3D1; font-size: 16px; line-height: 1.5; margin: 0 0 24px 0;">
                                You are receiving this email because we received a password reset request for your account.
                            </p>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin: 0 0 24px 0;">
                                <tr>
                                    <td style="border-radius: 8px; background-color: #7c3aed;">
                                        <a href="{{ $url }}" target="_blank" style="display: inline-block; padding: 14px 28px; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: bold;">
                                            {{ __("messages.auth.reset_password") }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="color: #9ca3af; font-size: 14px; line-height: 1.5; margin: 0 0 16px 0;">
                                This password reset link will expire in 60 minutes.
                            </p>
                            <p style="color: #9ca3af; font-size: 14px; line-height: 1.5; margin: 0;">
                                If you did not request a password reset, no further action is required.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
