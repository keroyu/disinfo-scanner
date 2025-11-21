<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class PasswordChangeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => '密碼驗證失敗',
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['sometimes', 'required', 'string'],
            'new_password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',      // At least one uppercase
                'regex:/[a-z]/',      // At least one lowercase
                'regex:/[0-9]/',      // At least one number
                'regex:/[!@#$%^&*(),.?":{}|<>]/', // At least one special char
                'confirmed',
            ],
            'new_password_confirmation' => ['required', 'string'],
        ];
    }

    /**
     * Get custom validation messages in Traditional Chinese.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'current_password.required' => '請輸入目前的密碼',
            'new_password.required' => '請輸入新密碼',
            'new_password.min' => '密碼長度至少需要 8 個字元',
            'new_password.regex' => '密碼必須包含大寫字母、小寫字母、數字和特殊字元',
            'new_password.confirmed' => '密碼確認不符',
            'new_password_confirmation.required' => '請確認您的新密碼',
        ];
    }
}
