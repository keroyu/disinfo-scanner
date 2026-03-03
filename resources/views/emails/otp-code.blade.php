<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $purpose === 'register' ? '帳號註冊驗證碼' : '登入驗證碼' }} - DISINFO_SCANNER</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 40px 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .logo { text-align: center; margin-bottom: 24px; font-size: 20px; font-weight: bold; color: #1d4ed8; }
        h1 { font-size: 22px; color: #111; margin-bottom: 8px; text-align: center; }
        .subtitle { color: #6b7280; font-size: 14px; text-align: center; margin-bottom: 32px; }
        .otp-box { background: #eff6ff; border: 2px solid #bfdbfe; border-radius: 8px; padding: 24px; text-align: center; margin: 24px 0; }
        .otp-code { font-size: 42px; font-weight: bold; letter-spacing: 12px; color: #1d4ed8; font-family: 'Courier New', monospace; }
        .expiry { color: #6b7280; font-size: 13px; margin-top: 12px; }
        .warning { background: #fef9c3; border-left: 4px solid #eab308; padding: 12px 16px; border-radius: 4px; font-size: 13px; color: #713f12; margin-top: 24px; }
        .footer { margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">DISINFO_SCANNER</div>

        <h1>{{ $purpose === 'register' ? '帳號註冊驗證碼' : '登入驗證碼' }}</h1>
        <p class="subtitle">
            @if($purpose === 'register')
                感謝您註冊 DISINFO_SCANNER！請使用以下驗證碼完成帳號建立。
            @else
                您正在登入 DISINFO_SCANNER，請使用以下驗證碼完成登入。
            @endif
        </p>

        <div class="otp-box">
            <div class="otp-code">{{ $otpCode }}</div>
            <div class="expiry">驗證碼有效期限：{{ $expirationMinutes }} 分鐘</div>
        </div>

        <div class="warning">
            <strong>注意：</strong>請勿將此驗證碼分享給任何人。DISINFO_SCANNER 的工作人員絕不會主動索取您的驗證碼。
        </div>

        <div class="footer">
            <p>若您並未請求此驗證碼，請忽略此郵件。</p>
            <p>&copy; {{ date('Y') }} DISINFO_SCANNER. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
