<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        $productId = $this->route('payment_product') ?? $this->route('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'portaly_product_id' => [
                'required',
                'string',
                'max:100',
                Rule::unique('payment_products', 'portaly_product_id')
                    ->ignore($productId)
                    ->whereNull('deleted_at'),
            ],
            'portaly_url' => ['required', 'string', 'max:500', 'url'],
            'price' => ['required', 'integer', 'min:1'],
            'currency' => ['sometimes', 'string', 'max:3'],
            'duration_days' => ['required_if:action_type,extend_premium', 'nullable', 'integer', 'min:1'],
            'action_type' => ['required', 'string', Rule::in(['extend_premium'])],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '商品名稱為必填',
            'portaly_product_id.required' => 'Product ID 為必填',
            'portaly_product_id.unique' => '此 Product ID 已存在',
            'portaly_url.required' => 'Portaly 連結為必填',
            'portaly_url.url' => 'Portaly 連結格式不正確',
            'price.required' => '價格為必填',
            'price.min' => '價格必須大於 0',
            'price.integer' => '價格必須是整數',
            'duration_days.required_if' => '會員天數為必填',
            'duration_days.min' => '會員天數必須大於 0',
            'duration_days.integer' => '會員天數必須是整數',
        ];
    }
}
