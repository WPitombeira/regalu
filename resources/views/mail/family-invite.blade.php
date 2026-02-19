<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; padding: 40px;">
        <h1 style="color: #1a1a2e; font-size: 24px; margin-bottom: 16px;">
            You've been invited to join {{ $familyName }}!
        </h1>
        <p style="color: #4a4a4a; font-size: 16px; line-height: 1.5; margin-bottom: 24px;">
            A family member has invited you to join their family group on {{ config('app.name') }}.
            Click the button below to accept the invitation and start sharing wishlists.
        </p>
        <a href="{{ $joinUrl }}"
           style="display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-size: 16px; font-weight: 600;">
            Join Family
        </a>
        <p style="color: #9a9a9a; font-size: 12px; margin-top: 32px;">
            If you did not expect this invitation, you can safely ignore this email.
        </p>
    </div>
</body>
</html>
