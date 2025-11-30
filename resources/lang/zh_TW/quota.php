<?php

/**
 * Traditional Chinese translations for API Quota messages (T429).
 */
return [
    // Quota status messages
    'unlimited' => '您擁有無限配額',
    'available' => '配額充足',
    'exceeded' => '您已達到本月配額上限 (:used/:limit)。請完成身份驗證以獲得無限配額。',
    'exceeded_short' => '已達到配額上限',

    // Role-based messages
    'admin_unlimited' => '管理員擁有無限配額',
    'editor_unlimited' => '網站編輯擁有無限配額',
    'regular_not_allowed' => '一般會員無法使用官方 API 匯入功能',

    // Error messages
    'permission_denied' => '需升級為高級會員',
    'upgrade_message' => '升級為高級會員後即可使用官方 API 匯入功能',
    'verification_suggestion' => '請完成身份驗證以獲得無限配額',

    // Usage display
    'monthly_usage' => '本月 API 用量',
    'usage_display' => ':used/:limit',
    'unlimited_display' => '無限制',
    'remaining' => '剩餘 :count 次',

    // Actions
    'verify_identity' => '驗證身份',
    'upgrade_to_premium' => '升級為高級會員',

    // Reset messages
    'reset_on' => '配額將於 :date 重置',
    'reset_first_of_month' => '配額將於每月 1 日重置',
];
