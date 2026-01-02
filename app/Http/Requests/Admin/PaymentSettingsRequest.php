<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PaymentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'portaly_webhook_secret' => ['required', 'string', 'min:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'portaly_webhook_secret.required' => 'Webhook 金鑰為必填',
            'portaly_webhook_secret.min' => 'Webhook 金鑰至少需要 10 個字元',
        ];
    }
}
