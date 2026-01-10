<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>付款成功</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; border-radius: 5px; padding: 30px; margin-bottom: 20px;">
        <h1 style="color: #28a745; margin-top: 0;">付款成功！</h1>

        <p>您好，{{ $user->name }}，</p>

        <p>感謝您的購買！您已成功完成以下方案的付款：</p>

        <div style="background-color: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p style="margin: 0;"><strong>已購買方案：</strong>{{ $productName }}</p>
            <p style="margin: 10px 0 0 0;"><strong>會員到期日：</strong>{{ $expiryDate }}</p>
        </div>

        <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
            <p style="margin: 0; color: #856404;">
                <strong>重要提醒：</strong>為確保您的會員功能完成升級，請<strong>重新登入</strong>您的帳號。
            </p>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $siteUrl }}"
               style="display: inline-block; background-color: #007bff; color: white; padding: 14px 40px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                前往網站
            </a>
        </div>

        <p>如需了解更多關於會員功能和積分機制，請參考網站上的「<a href="{{ $pointSystemUrl }}" style="color: #007bff; text-decoration: none;">積分系統說明</a>」頁面。</p>

        <p style="margin-top: 30px;">若有任何疑問，歡迎聯繫客服信箱：<br>
            <a href="mailto:{{ $supportEmail }}" style="color: #007bff; text-decoration: none;">{{ $supportEmail }}</a>
        </p>
    </div>

    <div style="font-size: 12px; color: #6c757d; text-align: center;">
        <p>此郵件由系統自動發送，請勿直接回覆。</p>
        <p>&copy; {{ date('Y') }} 投好壯壯有限公司 DISINFO SCANNER. All rights reserved.</p>
    </div>
</body>
</html>
