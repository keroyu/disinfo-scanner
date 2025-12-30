<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BatchEmailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()?->hasRole('administrator') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_ids' => 'required|array|min:1|max:100',
            'user_ids.*' => 'integer|exists:users,id',
            'subject' => 'required|string|max:200',
            'body' => 'required|string|max:10000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.required' => '請先選擇用戶',
            'user_ids.min' => '請先選擇用戶',
            'user_ids.max' => '批次郵件最多只能發送給 100 位用戶',
            'user_ids.*.exists' => '選擇的用戶不存在',
            'subject.required' => '請輸入郵件主題',
            'subject.max' => '郵件主題不能超過 200 個字元',
            'body.required' => '請輸入郵件內容',
            'body.max' => '郵件內容不能超過 10000 個字元',
        ];
    }
}
