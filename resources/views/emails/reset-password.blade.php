<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>密碼重設</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; border-radius: 5px; padding: 30px; margin-bottom: 20px;">
        <h1 style="color: #dc3545; margin-top: 0;">密碼重設請求</h1>

        <p>您好，</p>

        <p>我們收到您的密碼重設請求。請點擊下方按鈕重設您的密碼：</p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $resetUrl }}"
               style="display: inline-block; background-color: #dc3545; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                重設密碼
            </a>
        </div>

        <p>或複製以下連結到瀏覽器：</p>
        <p style="background-color: #e9ecef; padding: 10px; border-radius: 3px; word-break: break-all;">
            {{ $resetUrl }}
        </p>

        <p style="color: #dc3545; margin-top: 20px;">
            <strong>注意：</strong> 此重設連結將在 1 小時後過期。
        </p>

        <p>如果您沒有請求密碼重設，請忽略此郵件，您的密碼將保持不變。</p>
    </div>

    <div style="font-size: 12px; color: #6c757d; text-align: center;">
        <p>此郵件由系統自動發送，請勿直接回覆。</p>
    </div>
</body>
</html>
