<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; padding: 40px;">
        <h1 style="color: #1a1a2e; font-size: 24px; margin-bottom: 16px;">
            {{ __('assignments.mail_subject') }}
        </h1>
        <p style="color: #4a4a4a; font-size: 16px; line-height: 1.5; margin-bottom: 8px;">
            {{ __('assignments.greeting', ['name' => $drawerName]) }}
        </p>
        <p style="color: #4a4a4a; font-size: 16px; line-height: 1.5; margin-bottom: 24px;">
            {{ $targetName === null ? __('assignments.hidden_recipient') : __('assignments.you_drew', ['name' => $targetName]) }}
        </p>
        @if ($wishlistUrl)
            <p style="color: #4a4a4a; font-size: 16px; line-height: 1.5; margin-bottom: 24px;">
                {{ __('assignments.mail_intro') }}
            </p>
            <a href="{{ $wishlistUrl }}"
               style="display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-size: 16px; font-weight: 600;">
                {{ __('assignments.open') }}
            </a>
        @endif
        <p style="color: #9a9a9a; font-size: 12px; margin-top: 32px;">
            {{ __('assignments.mail_footer', ['app' => config('app.name')]) }}
        </p>
    </div>
</body>
</html>
