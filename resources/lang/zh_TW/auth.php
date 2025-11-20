<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines (Traditional Chinese)
    |--------------------------------------------------------------------------
    */

    'failed' => '電子郵件或密碼不正確。',
    'password' => '密碼錯誤。',
    'throttle' => '嘗試登入次數過多，請在 :seconds 秒後再試。',

    // Custom authentication messages
    'registration' => [
        'success' => '註冊成功，請檢查您的電子郵件以驗證帳號。',
        'email_exists' => '此電子郵件已被註冊。',
        'rate_limit' => '您已達到驗證郵件發送次數上限，請稍後再試。',
    ],

    'verification' => [
        'success' => '電子郵件驗證成功，您現在可以登入。',
        'invalid_token' => '無效或已過期的驗證連結。',
        'already_verified' => '此帳號已經驗證完成。',
        'token_used' => '此驗證連結已被使用。',
        'token_expired' => '驗證連結已過期，請重新發送驗證郵件。',
        'resend_success' => '驗證郵件已重新發送。',
    ],

    'login' => [
        'success' => '登入成功。',
        'failed' => '電子郵件或密碼不正確。',
        'unverified' => '請先驗證您的電子郵件。',
        'default_password' => '請立即更改您的預設密碼。',
    ],

    'password' => [
        'change_success' => '密碼已成功更改。',
        'change_required' => '您必須更改預設密碼才能繼續使用。',
        'reset_link_sent' => '密碼重設連結已發送到您的電子郵件。',
        'reset_success' => '密碼已成功重設。',
        'reset_invalid_token' => '無效或已過期的重設連結。',
        'reset_rate_limit' => '請求過於頻繁，請 1 小時後再試。',
        'weak_password' => '密碼強度不足，請使用更強的密碼。',
        'current_incorrect' => '目前密碼不正確。',
        'validation' => [
            'min_length' => '密碼長度至少需要 8 個字元',
            'uppercase' => '密碼必須包含至少一個大寫字母',
            'lowercase' => '密碼必須包含至少一個小寫字母',
            'number' => '密碼必須包含至少一個數字',
            'special' => '密碼必須包含至少一個特殊字元',
        ],
    ],

    'logout' => [
        'success' => '已成功登出。',
    ],

    'permissions' => [
        'denied' => '您沒有權限執行此操作。',
        'require_login' => '請登入會員。',
        'require_paid' => '需升級為付費會員。',
        'require_admin' => '需要管理員權限。',
    ],
];
