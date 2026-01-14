<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>帳號權限變更通知</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8fafc; border-radius: 8px; padding: 30px;">
        <div style="margin-bottom: 20px;">
            <h1 style="color: #1a202c; font-size: 24px; margin: 0 0 10px 0;">{{ config('app.name') }}</h1>
        </div>

        <div style="background-color: #ffffff; border-radius: 6px; padding: 25px; margin-bottom: 20px;">
            <p style="margin: 0 0 15px 0;">親愛的 {{ $userName }}：</p>

            @if($isSuspended)
                {{-- Suspended user notification (FR-061) --}}
                <p style="margin: 0 0 15px 0;">您的帳號權限已更新為「<strong style="color: #dc2626;">{{ $newRoleName }}</strong>」。</p>

                <div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0;">
                    <p style="margin: 0; color: #991b1b;">
                        <strong>您的帳號已被停權，如有疑問請聯繫管理員。</strong>
                    </p>
                </div>
            @else
                {{-- Non-suspended user notification --}}
                <p style="margin: 0 0 15px 0;">您的帳號權限已更新為「<strong style="color: #2563eb;">{{ $newRoleName }}</strong>」。</p>

                @if($wasUnsuspended)
                    {{-- Unsuspended notification --}}
                    <div style="background-color: #f0fdf4; border-left: 4px solid #22c55e; padding: 15px; margin: 20px 0;">
                        <p style="margin: 0; color: #166534;">
                            <strong>您的帳號已恢復正常。</strong>
                        </p>
                    </div>
                @endif

                @if($isPremiumMember)
                    {{-- Premium Member specific content (FR-058, FR-059) --}}
                    <div style="background-color: #eff6ff; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0;">
                        <p style="margin: 0 0 10px 0; color: #1e40af;">
                            <strong>您的高級會員資格將於 {{ $premiumExpiresAt }} 到期。</strong>
                        </p>
                        <p style="margin: 0; color: #1e40af;">
                            您現在可以使用網站所有功能並累積積分。
                        </p>
                    </div>
                @endif

                {{-- Links to Terms and Points Guide (FR-060) --}}
                <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                    <p style="margin: 0 0 10px 0; color: #64748b; font-size: 14px;">了解更多：</p>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li style="margin-bottom: 5px;">
                            <a href="{{ $termsUrl }}" style="color: #2563eb; text-decoration: none;">服務條款</a>
                        </li>
                        <li>
                            <a href="{{ $pointsGuideUrl }}" style="color: #2563eb; text-decoration: none;">積分系統說明</a>
                        </li>
                    </ul>
                </div>
            @endif
        </div>

        <div style="color: #718096; font-size: 12px; text-align: center; padding-top: 20px; border-top: 1px solid #e2e8f0;">
            <p style="margin: 0 0 10px 0;">謝謝您的使用！</p>
            <p style="margin: 0;">{{ config('app.name') }} 團隊</p>
            <p style="margin: 10px 0 0 0; color: #94a3b8;">此郵件由系統自動發送，請勿直接回覆。</p>
        </div>
    </div>
</body>
</html>
