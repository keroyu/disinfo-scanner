<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BatchRoleChangeRequest extends FormRequest
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
            'role_id' => 'required|integer|exists:roles,id',
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
            'user_ids.max' => '批次操作最多只能選擇 100 位用戶',
            'user_ids.*.exists' => '選擇的用戶不存在',
            'role_id.required' => '請選擇角色',
            'role_id.exists' => '選擇的角色不存在',
        ];
    }
}
