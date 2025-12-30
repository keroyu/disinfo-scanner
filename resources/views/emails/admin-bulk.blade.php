<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8fafc; border-radius: 8px; padding: 30px;">
        <div style="margin-bottom: 20px;">
            <h1 style="color: #1a202c; font-size: 24px; margin: 0 0 10px 0;">{{ config('app.name') }}</h1>
        </div>

        <div style="background-color: #ffffff; border-radius: 6px; padding: 25px; margin-bottom: 20px;">
            {!! nl2br(e($body)) !!}
        </div>

        <div style="color: #718096; font-size: 12px; text-align: center; padding-top: 20px; border-top: 1px solid #e2e8f0;">
            <p style="margin: 0;">此郵件由 {{ config('app.name') }} 系統發送</p>
        </div>
    </div>
</body>
</html>
