<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines (Traditional Chinese)
    |--------------------------------------------------------------------------
    */

    'accepted' => ':attribute 必須接受。',
    'active_url' => ':attribute 不是一個有效的網址。',
    'after' => ':attribute 必須要晚於 :date。',
    'after_or_equal' => ':attribute 必須要等於 :date 或更晚。',
    'alpha' => ':attribute 只能包含字母。',
    'alpha_dash' => ':attribute 只能包含字母、數字、破折號(-)及底線(_)。',
    'alpha_num' => ':attribute 只能包含字母和數字。',
    'array' => ':attribute 必須是一個陣列。',
    'before' => ':attribute 必須要早於 :date。',
    'before_or_equal' => ':attribute 必須要等於 :date 或更早。',
    'between' => [
        'numeric' => ':attribute 必須介於 :min 和 :max 之間。',
        'file' => ':attribute 必須介於 :min 和 :max KB 之間。',
        'string' => ':attribute 必須介於 :min 和 :max 個字元之間。',
        'array' => ':attribute 必須包含 :min 至 :max 個項目。',
    ],
    'boolean' => ':attribute 必須為布林值。',
    'confirmed' => ':attribute 確認不符合。',
    'date' => ':attribute 不是一個有效的日期。',
    'date_equals' => ':attribute 必須等於 :date。',
    'date_format' => ':attribute 不符合格式 :format。',
    'different' => ':attribute 和 :other 必須不同。',
    'digits' => ':attribute 必須是 :digits 位數字。',
    'digits_between' => ':attribute 必須介於 :min 和 :max 位數之間。',
    'dimensions' => ':attribute 圖片尺寸不正確。',
    'distinct' => ':attribute 已經存在。',
    'email' => ':attribute 必須是一個有效的電子郵件地址。',
    'ends_with' => ':attribute 必須以下列之一結尾：:values。',
    'exists' => '所選擇的 :attribute 無效。',
    'file' => ':attribute 必須是一個檔案。',
    'filled' => ':attribute 不能留空。',
    'gt' => [
        'numeric' => ':attribute 必須大於 :value。',
        'file' => ':attribute 必須大於 :value KB。',
        'string' => ':attribute 必須多於 :value 個字元。',
        'array' => ':attribute 必須多於 :value 個項目。',
    ],
    'gte' => [
        'numeric' => ':attribute 必須大於或等於 :value。',
        'file' => ':attribute 必須大於或等於 :value KB。',
        'string' => ':attribute 必須多於或等於 :value 個字元。',
        'array' => ':attribute 必須多於或等於 :value 個項目。',
    ],
    'image' => ':attribute 必須是一張圖片。',
    'in' => '所選擇的 :attribute 無效。',
    'in_array' => ':attribute 沒有在 :other 中。',
    'integer' => ':attribute 必須是整數。',
    'ip' => ':attribute 必須是一個有效的 IP 位址。',
    'ipv4' => ':attribute 必須是一個有效的 IPv4 位址。',
    'ipv6' => ':attribute 必須是一個有效的 IPv6 位址。',
    'json' => ':attribute 必須是正確的 JSON 字串。',
    'lt' => [
        'numeric' => ':attribute 必須小於 :value。',
        'file' => ':attribute 必須小於 :value KB。',
        'string' => ':attribute 必須少於 :value 個字元。',
        'array' => ':attribute 必須少於 :value 個項目。',
    ],
    'lte' => [
        'numeric' => ':attribute 必須小於或等於 :value。',
        'file' => ':attribute 必須小於或等於 :value KB。',
        'string' => ':attribute 必須少於或等於 :value 個字元。',
        'array' => ':attribute 必須少於或等於 :value 個項目。',
    ],
    'max' => [
        'numeric' => ':attribute 不能大於 :max。',
        'file' => ':attribute 不能大於 :max KB。',
        'string' => ':attribute 不能多於 :max 個字元。',
        'array' => ':attribute 最多只能有 :max 個項目。',
    ],
    'mimes' => ':attribute 必須是 :values 類型的檔案。',
    'mimetypes' => ':attribute 必須是 :values 類型的檔案。',
    'min' => [
        'numeric' => ':attribute 不能小於 :min。',
        'file' => ':attribute 不能小於 :min KB。',
        'string' => ':attribute 不能少於 :min 個字元。',
        'array' => ':attribute 至少要有 :min 個項目。',
    ],
    'not_in' => '所選擇的 :attribute 無效。',
    'not_regex' => ':attribute 格式無效。',
    'numeric' => ':attribute 必須是數字。',
    'password' => '密碼錯誤。',
    'present' => ':attribute 必須存在。',
    'regex' => ':attribute 格式無效。',
    'required' => ':attribute 不能留空。',
    'required_if' => '當 :other 是 :value 時，:attribute 不能留空。',
    'required_unless' => '當 :other 不是 :values 時，:attribute 不能留空。',
    'required_with' => '當 :values 出現時，:attribute 不能留空。',
    'required_with_all' => '當 :values 出現時，:attribute 不能留空。',
    'required_without' => '當 :values 不出現時，:attribute 不能留空。',
    'required_without_all' => '當 :values 都不出現時，:attribute 不能留空。',
    'same' => ':attribute 和 :other 必須相同。',
    'size' => [
        'numeric' => ':attribute 的大小必須是 :size。',
        'file' => ':attribute 的大小必須是 :size KB。',
        'string' => ':attribute 必須是 :size 個字元。',
        'array' => ':attribute 必須包含 :size 個項目。',
    ],
    'starts_with' => ':attribute 必須以下列之一開頭：:values。',
    'string' => ':attribute 必須是一個字串。',
    'timezone' => ':attribute 必須是一個正確的時區。',
    'unique' => ':attribute 已經存在。',
    'uploaded' => ':attribute 上傳失敗。',
    'url' => ':attribute 格式無效。',
    'uuid' => ':attribute 必須是有效的 UUID。',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'email' => [
            'required' => '電子郵件為必填欄位',
            'email' => '請輸入有效的電子郵件格式',
            'unique' => '此電子郵件已被註冊',
            'max' => '電子郵件長度不得超過 :max 個字元',
        ],
        'password' => [
            'required' => '密碼為必填欄位',
            'min' => '密碼長度至少需要 :min 個字元',
            'confirmed' => '密碼確認不符合',
            'regex' => '密碼格式不符合要求（需包含大小寫字母、數字及特殊字元）',
        ],
        'current_password' => [
            'required' => '請輸入目前的密碼',
        ],
        'password_confirmation' => [
            'required' => '請確認您的新密碼',
        ],
        'name' => [
            'required' => '商品名稱為必填',
        ],
        'portaly_product_id' => [
            'required' => 'Product ID 為必填',
            'unique' => '此 Product ID 已存在',
        ],
        'portaly_url' => [
            'required' => 'Portaly 連結為必填',
            'url' => 'Portaly 連結格式不正確',
        ],
        'price' => [
            'required' => '價格為必填',
            'min' => '價格必須大於 0',
            'integer' => '價格必須是整數',
        ],
        'duration_days' => [
            'required' => '會員天數為必填',
            'min' => '會員天數必須大於 0',
            'integer' => '會員天數必須是整數',
        ],
        'portaly_webhook_secret' => [
            'required' => 'Webhook 金鑰為必填',
            'min' => 'Webhook 金鑰至少需要 :min 個字元',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'email' => '電子郵件',
        'password' => '密碼',
        'current_password' => '目前密碼',
        'new_password' => '新密碼',
        'password_confirmation' => '密碼確認',
        'name' => '名稱',
        'youtube_api_key' => 'YouTube API 金鑰',
        'verification_method' => '驗證方式',
        'portaly_product_id' => 'Product ID',
        'portaly_url' => 'Portaly 連結',
        'portaly_webhook_secret' => 'Webhook 金鑰',
        'duration_days' => '會員天數',
        'action_type' => '動作類型',
        'status' => '狀態',
    ],
];
