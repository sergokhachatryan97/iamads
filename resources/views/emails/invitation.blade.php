<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('You\'ve been invited') }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h1 style="color: #4f46e5; margin-top: 0;">{{ __('You\'ve been invited!') }}</h1>
        <p>{{ __('You\'ve been invited to join') }} <strong>{{ config('app.name') }}</strong> {{ __('as a') }} <strong>{{ ucfirst($invitation->role) }}</strong>.</p>
    </div>

    <div style="background-color: #ffffff; padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 20px;">
        <p>{{ __('Click the button below to accept your invitation and set up your profile:') }}</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ route('staff.invitation.accept', $token) }}" style="display: inline-block; padding: 12px 24px; background-color: #4f46e5; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600;">
                {{ __('Accept Invitation') }}
            </a>
        </div>
        <p style="font-size: 12px; color: #6b7280; margin-top: 20px;">
            {{ __('Or copy and paste this link into your browser:') }}<br>
            <a href="{{ route('staff.invitation.accept', $token) }}" style="color: #4f46e5; word-break: break-all;">
                {{ route('staff.invitation.accept', $token) }}
            </a>
        </p>
        <p style="font-size: 13px; color: #374151; margin-top: 15px;">
            {{ __('After clicking, you will be taken to your profile page where you can complete your information and set your password.') }}
        </p>
    </div>

    <div style="background-color: #fef3c7; padding: 15px; border-left: 4px solid #f59e0b; border-radius: 4px; margin-bottom: 20px;">
        <p style="margin: 0; font-size: 14px;">
            <strong>{{ __('Important:') }}</strong> {{ __('This invitation link expires in 48 hours.') }}
        </p>
    </div>

    <p style="font-size: 12px; color: #6b7280; text-align: center; margin-top: 30px;">
        {{ __('If you did not expect this invitation, you can safely ignore this email.') }}
    </p>
</body>
</html>

