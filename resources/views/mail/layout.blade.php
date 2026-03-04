<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #070715; color: #C3C3D1;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #070715; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%;">
                    {{-- Logo header --}}
                    <tr>
                        <td align="center" style="padding-bottom: 32px;">
                            <img src="{{ asset('media/logo.png') }}" alt="{{ config('app.name') }}" height="40" style="height: 40px;">
                            <span style="display: inline-block; vertical-align: middle; margin-left: 12px; font-size: 24px; font-weight: 600; color: #ffffff;">
                                {{ config('app.name') }}
                            </span>
                        </td>
                    </tr>

                    {{-- Content card --}}
                    <tr>
                        <td style="background-color: #111128; border-radius: 12px; padding: 40px; border: 1px solid #1e1e3a;">
                            {{ $slot }}
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="padding-top: 32px; padding-bottom: 16px;">
                            <p style="margin: 0; font-size: 13px; color: #6b6b80; line-height: 1.5;">
                                &copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('messages.footer.rights') }}.
                            </p>
                            <p style="margin: 8px 0 0; font-size: 12px; color: #4a4a5e; line-height: 1.5;">
                                If you no longer wish to receive these emails, you can update your notification preferences in your account settings.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
